<?php

namespace App\Http\Controllers\Commercial;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\CorrectionRequest;
use App\Http\Requests\Commercial\CommercialActionRequest;
use App\Http\Requests\Commercial\FilterServiceRequests;
use App\Models\ClientAccessToken;
use App\Models\NotificationRecord;
use App\Models\OfferedService;
use App\Models\RequestFile;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestHistory;
use App\Models\User;
use App\Services\ClientAccessService;
use App\Services\ClientRevisionService;
use App\Services\CommercialIndicators;
use App\Services\CommercialWorkflowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ServiceRequestController extends Controller
{
    public function index(FilterServiceRequests $request, CommercialIndicators $indicators)
    {
        Gate::authorize('viewAny', ServiceRequest::class);
        $data = $request->validated();
        $visible = ServiceRequest::visibleTo($request->user());
        $filtered = $this->filterRequests(clone $visible, $data);
        $requests = (clone $filtered)->with(['currentQuotation', 'assignee'])->latest('created_at')->latest('id')->paginate(15)->withQueryString();
        $unassigned = $this->unassignedQueue($data);
        $period = $indicators->period($data);
        $metrics = $indicators->commercialMetrics($request->user(), $period);
        $overview = $indicators->overview($request->user(), $period, $filtered);
        $services = OfferedService::orderBy('name')->get(['id', 'name']);
        $histories = $this->filteredHistory($filtered, $data);

        return view('commercial.index', compact('requests', 'unassigned', 'services', 'histories', 'overview') + $metrics);
    }

    private function unassignedQueue(array $filters)
    {
        $queue = ServiceRequest::select(['code', 'service', 'project_type', 'created_at', 'status', 'lock_version'])
            ->whereNull('assigned_to')->whereIn('status', ['pendiente', 'evaluacion', 'observada', 'aprobado']);

        return $this->filterRequests($queue, $filters, minimal: true)->oldest('created_at')->oldest('id')->paginate(10, ['*'], 'queue_page')->withQueryString();
    }

    private function filteredHistory(Builder $filtered, array $filters)
    {
        return ServiceRequestHistory::with('serviceRequest:id,code')
            ->whereIn('service_request_id', (clone $filtered)->select('id'))
            ->when($filters['history_action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->latest('created_at')->latest('id')->paginate(15, ['*'], 'history_page')->withQueryString();
    }

    private function filterRequests(Builder $query, array $data, bool $minimal = false): Builder
    {
        return $query
            ->when($data['status'] ?? null, fn ($q, $value) => $q->where('status', $value))
            ->when($data['service_id'] ?? null, fn ($q, $value) => $q->where('service_id', $value))
            ->when($data['from'] ?? null, fn ($q, $value) => $q->where('created_at', '>=', $value.' 00:00:00'))
            ->when($data['to'] ?? null, fn ($q, $value) => $q->where('created_at', '<=', $value.' 23:59:59'))
            ->when($data['search'] ?? null, fn ($q, $value) => $q->where(function ($search) use ($value, $minimal) {
                $search->where('code', 'like', '%'.$value.'%');
                if (! $minimal) {
                    $search->orWhere('client_name', 'like', '%'.$value.'%')->orWhere('client_email', 'like', '%'.$value.'%');
                }
            }));
    }

    public function show(ServiceRequest $serviceRequest)
    {
        Gate::authorize('view', $serviceRequest);
        $serviceRequest->load(['deliverables.latestVersion', 'deliverables.quotation']);
        $serviceRequest->load(['assignee', 'currentRevision', 'currentQuotation', 'files', 'quotations' => fn ($q) => $q->orderByDesc('version'), 'histories' => fn ($q) => $q->orderBy('id'), 'histories.user', 'evaluations.user']);
        $responsibles = User::where('is_active', true)->whereIn('role', ['admin', 'gestor'])->orderBy('name')->get();
        $notices = NotificationRecord::with('deliveries')->where(function ($q) use ($serviceRequest) {
            $q->where(fn ($q) => $q->where('kind', 'received')->where('resource_id', $serviceRequest->id))
                ->orWhere(fn ($q) => $q->whereIn('kind', ['observed', 'corrected'])->whereIn('resource_id', $serviceRequest->revisions()->select('id')))
                ->orWhere(fn ($q) => $q->where('kind', 'quotation')->whereIn('resource_id', $serviceRequest->quotations()->select('id')))
                ->orWhere(fn ($q) => $q->whereIn('kind', ['access', 'email_change'])->whereIn('resource_id', ClientAccessToken::where('service_request_id', $serviceRequest->id)->select('id')));
        })->latest('id')->get();

        return view('commercial.show', compact('serviceRequest', 'responsibles', 'notices'));
    }

    public function showDemoAccess(Request $request, ServiceRequest $serviceRequest)
    {
        Gate::authorize('operate', $serviceRequest);
        $demoUrl = $request->session()->get('demo_access_url');
        $response = response()->view('commercial.demo-access', compact('serviceRequest', 'demoUrl'));
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }

    public function prepareDemoAccess(Request $request, ServiceRequest $serviceRequest, ClientAccessService $access)
    {
        $demoUrl = $access->prepareDemoLink($serviceRequest, $request->user());

        return redirect()->route('commercial.requests.demo-access.show', $serviceRequest)
            ->with('demo_access_url', $demoUrl);
    }

    public function transition(CommercialActionRequest $request, ServiceRequest $serviceRequest, CommercialWorkflowService $workflow)
    {
        $workflow->transition($serviceRequest, $request->user(), $request->validated());

        return redirect()->route('commercial.requests.show', $serviceRequest)->with('status', 'Operación registrada.');
    }

    public function correct(CorrectionRequest $request, ServiceRequest $serviceRequest, ClientRevisionService $revisions)
    {
        Gate::authorize('operate', $serviceRequest);
        try {
            $revisions->correct($serviceRequest, $request->user(), $request->validated(), $request->file('attachment'));
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput($request->except('attachment'))->withErrors(['attachment' => 'No se confirmó la corrección. Revisa los datos y el archivo.']);
        }

        return redirect()->route('commercial.requests.show', $serviceRequest)->with('status', 'Corrección con evidencia registrada; se conservó el envío anterior y volvió a evaluación.');
    }

    public function attachment(Request $request, ServiceRequest $serviceRequest)
    {
        Gate::authorize('view', $serviceRequest);
        $file = $serviceRequest->files()->latest('id')->firstOrFail();

        return $this->fileResponse($request, $file);
    }

    public function file(Request $request, RequestFile $file)
    {
        Gate::authorize('view', $file->serviceRequest);

        return $this->fileResponse($request, $file);
    }

    private function fileResponse(Request $request, RequestFile $file)
    {
        $disk = Storage::disk('commercial');
        abort_unless($disk->exists($file->path), 404, 'Archivo no disponible.');
        $extension = strtolower(pathinfo($file->path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'pdf' => 'application/pdf','png' => 'image/png','jpg','jpeg' => 'image/jpeg',default => 'application/octet-stream'
        };
        $inline = $request->boolean('preview') && in_array($extension, ['pdf', 'png', 'jpg', 'jpeg']);

        return $disk->response($file->path, 'adjunto.'.$extension, ['Content-Type' => $mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "sandbox; default-src 'none'", 'X-Frame-Options' => 'DENY'], $inline ? 'inline' : 'attachment');
    }
}

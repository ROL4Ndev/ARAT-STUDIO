<?php

namespace App\Http\Controllers\Commercial;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\CorrectionRequest;
use App\Http\Requests\Commercial\CommercialActionRequest;
use App\Http\Requests\Commercial\FilterServiceRequests;
use App\Models\ClientAccessToken;
use App\Models\NotificationRecord;
use App\Models\OfferedService;
use App\Models\Quotation;
use App\Models\RequestFile;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestHistory;
use App\Models\User;
use App\Services\ClientRevisionService;
use App\Services\CommercialWorkflowService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ServiceRequestController extends Controller
{
    public function index(FilterServiceRequests $request)
    {
        Gate::authorize('viewAny', ServiceRequest::class);
        $data = $request->validated();
        $visible = ServiceRequest::query()->when($request->user()->role !== 'admin', fn ($q) => $q->where('assigned_to', $request->user()->id));
        $counts = (clone $visible)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $filtered = (clone $visible)->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))->when($data['service_id'] ?? null, fn ($q, $id) => $q->where('service_id', $id))->when($data['from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v.' 00:00:00'))->when($data['to'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', $v.' 23:59:59'))->when($data['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('code', 'like', '%'.$s.'%')->orWhere('client_name', 'like', '%'.$s.'%')->orWhere('client_email', 'like', '%'.$s.'%')));
        $requests = (clone $filtered)->with(['currentQuotation', 'assignee'])->latest('id')->paginate(15)->withQueryString();
        $unassigned = ServiceRequest::select(['code', 'service', 'project_type', 'created_at', 'status', 'lock_version'])->whereNull('assigned_to')->whereIn('status', ['pendiente', 'evaluacion', 'observada', 'aprobado'])->when($data['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->when($data['service_id'] ?? null, fn ($q, $v) => $q->where('service_id', $v))->when($data['from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v.' 00:00:00'))->when($data['to'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', $v.' 23:59:59'))->when($data['search'] ?? null, fn ($q, $v) => $q->where('code', 'like', '%'.$v.'%'))->oldest('id')->paginate(10, ['*'], 'queue_page')->withQueryString();
        $from = CarbonImmutable::parse($data['from'] ?? today()->subDays(30)->toDateString(), 'America/Lima')->startOfDay()->utc();
        $to = CarbonImmutable::parse($data['to'] ?? today()->toDateString(), 'America/Lima')->endOfDay()->utc();
        $cohort = (clone $visible)->whereRaw('(SELECT MIN(issued_at) FROM quotations WHERE quotations.service_request_id = service_requests.id) BETWEEN ? AND ?', [$from->format('Y-m-d H:i:s.u'), $to->format('Y-m-d H:i:s.u')]);
        $denominator = (clone $cohort)->count();
        $accepted = (clone $cohort)->where('closure_outcome', 'aceptada')->count();
        // created_at conserva la zona local histórica; issued_at es UTC explícito.
        $evaluated = (clone $visible)->whereBetween('created_at', [$from->timezone('America/Lima'), $to->timezone('America/Lima')])->whereNotNull('first_evaluated_at')->get(['created_at', 'first_evaluated_at']);
        $minutes = $evaluated->count() ? $evaluated->avg(fn ($r) => $r->created_at->diffInSeconds($r->first_evaluated_at) / 60) : null;
        $issued = Quotation::whereIn('service_request_id', (clone $visible)->select('id'))->whereBetween('issued_at', [$from, $to])->count();
        $services = OfferedService::orderBy('name')->get(['id', 'name']);
        $histories = ServiceRequestHistory::with('serviceRequest:id,code')->whereIn('service_request_id', (clone $filtered)->select('id'))->when($data['history_action'] ?? null, fn ($q, $v) => $q->where('action', $v))->latest('id')->paginate(15, ['*'], 'history_page')->withQueryString();

        return view('commercial.index', compact('requests', 'unassigned', 'counts', 'denominator', 'accepted', 'minutes', 'issued', 'services', 'histories'));
    }

    public function show(ServiceRequest $serviceRequest)
    {
        Gate::authorize('view', $serviceRequest);
        $serviceRequest->load(['assignee', 'currentRevision', 'files', 'quotations' => fn ($q) => $q->orderByDesc('version'), 'histories' => fn ($q) => $q->orderBy('id'), 'histories.user', 'evaluations.user']);
        $responsibles = User::where('is_active', true)->whereIn('role', ['admin', 'gestor'])->orderBy('name')->get();
        $notices = NotificationRecord::with('deliveries')->where(function ($q) use ($serviceRequest) {
            $q->where(fn ($q) => $q->where('kind', 'received')->where('resource_id', $serviceRequest->id))
                ->orWhere(fn ($q) => $q->whereIn('kind', ['observed', 'corrected'])->whereIn('resource_id', $serviceRequest->revisions()->select('id')))
                ->orWhere(fn ($q) => $q->where('kind', 'quotation')->whereIn('resource_id', $serviceRequest->quotations()->select('id')))
                ->orWhere(fn ($q) => $q->whereIn('kind', ['access', 'email_change'])->whereIn('resource_id', ClientAccessToken::where('service_request_id', $serviceRequest->id)->select('id')));
        })->latest('id')->get();

        return view('commercial.show', compact('serviceRequest', 'responsibles', 'notices'));
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

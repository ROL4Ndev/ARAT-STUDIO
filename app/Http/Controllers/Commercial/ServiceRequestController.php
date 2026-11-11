<?php

namespace App\Http\Controllers\Commercial;

use App\Http\Controllers\Controller;
use App\Http\Requests\Commercial\FilterServiceRequests;
use App\Http\Requests\Commercial\TransitionServiceRequest;
use App\Models\ServiceRequest;
use App\Services\CommercialWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ServiceRequestController extends Controller
{
    public function index(FilterServiceRequests $request)
    {
        $data = $request->validated();
        $counts = ServiceRequest::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $requests = ServiceRequest::query()->with('quotation')
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($data['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('code', 'like', '%'.$search.'%')->orWhere('client_name', 'like', '%'.$search.'%')->orWhere('client_email', 'like', '%'.$search.'%');
                });
            })->latest('id')->paginate(15)->withQueryString();

        return view('commercial.index', compact('counts', 'requests'));
    }

    public function show(ServiceRequest $serviceRequest)
    {
        $serviceRequest->load(['histories' => fn ($q) => $q->orderBy('created_at')->orderBy('id'), 'histories.user', 'quotation']);

        return view('commercial.show', compact('serviceRequest'));
    }

    public function transition(TransitionServiceRequest $request, ServiceRequest $serviceRequest, CommercialWorkflowService $workflow)
    {
        $workflow->transition($serviceRequest, $request->user(), $request->validated());

        return back()->with('status', 'Estado actualizado e historial registrado.');
    }

    public function attachment(Request $request, ServiceRequest $serviceRequest)
    {
        abort_unless($serviceRequest->attachment_path, 404);
        try {
            $disk = Storage::disk('commercial');
            abort_unless($disk->exists($serviceRequest->attachment_path), 404, 'El archivo no está disponible.');
            $extension = strtolower(pathinfo($serviceRequest->attachment_path, PATHINFO_EXTENSION));
            $mime = match ($extension) {
                'pdf' => 'application/pdf', 'jpg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', default => 'application/octet-stream'
            };
            $inline = $request->boolean('preview') && in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true);

            return $disk->response($serviceRequest->attachment_path, $serviceRequest->code.'.'.$extension, [
                'Content-Type' => $mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
                'Content-Security-Policy' => "sandbox; default-src 'none'", 'X-Frame-Options' => 'DENY',
            ], $inline ? 'inline' : 'attachment');
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            abort(503, 'No se pudo recuperar el archivo. Inténtalo nuevamente.');
        }
    }
}

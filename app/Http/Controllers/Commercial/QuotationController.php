<?php

namespace App\Http\Controllers\Commercial;

use App\Http\Controllers\Controller;
use App\Http\Requests\Commercial\IssueQuotationRequest;
use App\Http\Requests\Commercial\QuotationActionRequest;
use App\Models\ClientAccessToken;
use App\Models\NotificationRecord;
use App\Models\Quotation;
use App\Models\RequestRevision;
use App\Models\ServiceRequest;
use App\Models\WorkIntent;
use App\Services\AuditService;
use App\Services\IssueQuotationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class QuotationController extends Controller
{
    public function store(IssueQuotationRequest $request, ServiceRequest $serviceRequest, IssueQuotationService $service)
    {
        Gate::authorize('operate', $serviceRequest);
        $source = $request->filled('source_code') ? $serviceRequest->quotations()->where('code', $request->validated('source_code'))->firstOrFail() : null;
        $q = $service->draft($serviceRequest, $request->user(), $request->validated(), $source);

        return redirect()->route('commercial.quotations.show', $q)->with('status', 'Borrador disponible. La propuesta anterior no cambia hasta completar la nueva emisión.');
    }

    public function update(IssueQuotationRequest $request, Quotation $quotation, IssueQuotationService $service)
    {
        $q = $service->edit($quotation, $request->user(), $request->validated());

        return redirect()->route('commercial.quotations.show', $q)->with('status', 'Borrador actualizado.');
    }

    public function prepare(QuotationActionRequest $request, Quotation $quotation, IssueQuotationService $service)
    {
        $service->prepare($quotation, $request->user(), (int) $request->validated('lock_version'));

        return redirect()->route('commercial.quotations.show', $quotation)->with('status', 'Trabajo durable preparado. El PDF se generará sin mantener bloqueos de base de datos.');
    }

    public function show(Quotation $quotation)
    {
        Gate::authorize('view', $quotation);
        $quotation->load(['user', 'serviceRequest']);
        $intent = WorkIntent::where('key', 'pdf:'.$quotation->id)->first();
        $notices = NotificationRecord::with('deliveries')->where('kind', 'quotation')->where('resource_id', $quotation->id)->get();

        return view('commercial.quotation', compact('quotation', 'intent', 'notices'));
    }

    public function download(Quotation $quotation)
    {
        Gate::authorize('view', $quotation);
        abort_unless($quotation->pdf_status === 'ready' && $quotation->pdf_path && $quotation->issued_at, 409, 'Esta versión todavía no tiene PDF emitido.');
        $disk = Storage::disk('commercial');
        abort_unless($disk->exists($quotation->pdf_path), 404, 'PDF no disponible.');
        abort_if($quotation->pdf_hash && ! hash_equals($quotation->pdf_hash, hash_file('sha256', $disk->path($quotation->pdf_path))), 409, 'El documento no está disponible para descarga. Contacta al estudio.');

        return $disk->download($quotation->pdf_path, $quotation->code.'.pdf', ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function retryNotice(QuotationActionRequest $request, NotificationRecord $notice)
    {
        $r = match ($notice->kind) {
            'quotation' => Quotation::findOrFail($notice->resource_id)->serviceRequest,'received' => ServiceRequest::findOrFail($notice->resource_id),'observed','corrected' => ServiceRequest::findOrFail(RequestRevision::findOrFail($notice->resource_id)->service_request_id),default => ServiceRequest::findOrFail(ClientAccessToken::findOrFail($notice->resource_id)->service_request_id)
        };
        Gate::authorize('operate', $r);
        DB::transaction(function () use ($notice, $r, $request) {
            ServiceRequest::whereKey($r->id)->lockForUpdate()->firstOrFail();
            $work = WorkIntent::where('key', 'notification:'.$notice->id)->lockForUpdate()->firstOrFail();
            $locked = NotificationRecord::whereKey($notice->id)->lockForUpdate()->firstOrFail();
            if (in_array($work->status, ['pending', 'processing', 'done'])) {
                return;
            }
            abort_unless($locked->status === 'failed' && $work->status === 'failed', 409, 'La notificación no admite reintento.');
            $work->update(['status' => 'pending', 'lease' => null, 'leased_at' => null]);
            $locked->update(['status' => 'pending']);
            app(AuditService::class)->record($r, 'notification_retry', 'Reintento autorizado', ['notification_id' => $notice->id, 'status' => 'failed'], ['notification_id' => $notice->id, 'status' => 'pending'], $request->user());
        }, 3);

        return back()->with('status', 'Reintento preparado. No se creará otra cotización.');
    }
}

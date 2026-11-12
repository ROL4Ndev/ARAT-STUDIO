<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ClientActionRequest;
use App\Http\Requests\Client\CorrectionRequest;
use App\Http\Requests\Client\ResponseRequest;
use App\Models\Quotation;
use App\Models\RequestFile;
use App\Policies\QuotationPolicy;
use App\Policies\ServiceRequestPolicy;
use App\Services\ClientAccessService;
use App\Services\ClientResponseService;
use App\Services\ClientRevisionService;
use App\Services\CommercialWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DossierController extends Controller
{
    public function show(Request $request)
    {
        $grant = $request->attributes->get('clientGrant');
        $dossier = $grant->serviceRequest;
        $dossier->load(['currentRevision', 'files', 'histories' => fn ($q) => $q->where('is_public', true)->orderBy('id'), 'quotations' => fn ($q) => $q->whereNotNull('issued_at')->where('pdf_status', 'ready')->orderByDesc('version')]);

        return view('client.dossier', compact('dossier', 'grant'));
    }

    public function correct(CorrectionRequest $request, ClientRevisionService $revisions)
    {
        $grant = $request->attributes->get('clientGrant');
        try {
            $revisions->correct($grant->serviceRequest, $grant, $request->validated(), $request->file('attachment'));
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput($request->except('attachment'))->withErrors(['attachment' => 'No se confirmó la corrección. Revisa el archivo o utiliza un enlace externo.']);
        }

        return redirect()->route('client.dossier')->with('status', 'Corrección registrada; se conservaron los datos y archivos anteriores.');
    }

    public function respond(ResponseRequest $request, Quotation $quotation, ClientResponseService $responses)
    {
        $responses->respond($quotation, $request->attributes->get('clientGrant'), $request->validated());

        return redirect()->route('client.dossier')->with('status', 'Respuesta registrada. Esta confirmación no es una firma digital certificada.');
    }

    public function cancel(ClientActionRequest $request, CommercialWorkflowService $workflow)
    {
        $g = $request->attributes->get('clientGrant');
        $workflow->cancel($g->serviceRequest, $g, (int) $request->validated('lock_version'), $request->validated('reason'));

        return redirect()->route('client.dossier')->with('status', 'Solicitud cancelada.');
    }

    public function email(ClientActionRequest $request, ClientAccessService $access)
    {
        $access->emailChange($request->attributes->get('clientGrant'), $request->validated('email'));

        return back()->with('status', 'Se preparó la verificación del nuevo correo. El correo actual no cambia hasta confirmar el enlace desde esta sesión.');
    }

    public function quotation(Request $request, Quotation $quotation)
    {
        abort_unless(app(QuotationPolicy::class)->clientView($request->attributes->get('clientGrant'), $quotation), 403);
        $disk = Storage::disk('commercial');
        abort_unless($disk->exists($quotation->pdf_path), 404, 'PDF no disponible.');
        abort_if($quotation->pdf_hash && ! hash_equals($quotation->pdf_hash, hash_file('sha256', $disk->path($quotation->pdf_path))), 409, 'El documento no está disponible para descarga. Contacta al estudio.');

        return $disk->download($quotation->pdf_path, $quotation->code.'.pdf', ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function file(Request $request, RequestFile $file)
    {
        abort_unless(app(ServiceRequestPolicy::class)->clientView($request->attributes->get('clientGrant'), $file->serviceRequest), 403);
        $disk = Storage::disk('commercial');
        abort_unless($disk->exists($file->path), 404);

        return $disk->download($file->path, 'adjunto.'.pathinfo($file->path, PATHINFO_EXTENSION), ['Content-Type' => 'application/octet-stream', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}

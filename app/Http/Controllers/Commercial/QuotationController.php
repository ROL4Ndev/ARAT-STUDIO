<?php

namespace App\Http\Controllers\Commercial;

use App\Http\Controllers\Controller;
use App\Http\Requests\Commercial\IssueQuotationRequest;
use App\Models\Quotation;
use App\Models\ServiceRequest;
use App\Services\IssueQuotationService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class QuotationController extends Controller
{
    public function store(IssueQuotationRequest $request, ServiceRequest $serviceRequest, IssueQuotationService $service)
    {
        try {
            $quotation = $service->issue($serviceRequest, $request->user(), $request->validated());
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()->withErrors(['scope' => 'No se pudo completar el PDF. No se confirmó la emisión; puedes reintentar.']);
        }

        return redirect()->route('commercial.quotations.show', $quotation)->with('status', 'Cotización oficial disponible.');
    }

    public function show(Quotation $quotation)
    {
        $quotation->load('user');

        return view('commercial.quotation', compact('quotation'));
    }

    public function download(Quotation $quotation)
    {
        try {
            $disk = Storage::disk('commercial');
            abort_unless($disk->exists($quotation->pdf_path), 404, 'El PDF no está disponible.');

            return $disk->download($quotation->pdf_path, $quotation->code.'.pdf', ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            abort(503, 'No se pudo recuperar el PDF. Inténtalo nuevamente.');
        }
    }
}

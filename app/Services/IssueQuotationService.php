<?php

namespace App\Services;

use App\Models\Quotation;
use App\Models\ServiceRequest;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IssueQuotationService
{
    public function __construct(private QuotationPdfService $pdf) {}

    public function issue(ServiceRequest $request, User $user, array $data): Quotation
    {
        abort_unless(in_array($user->role, ['admin', 'gestor'], true), 403);
        $path = null;
        try {
            // Sin reintentos automáticos: la transacción incluye escritura de archivos.
            return DB::transaction(function () use ($request, $user, $data, &$path) {
                $locked = ServiceRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
                if ($locked->status !== 'aprobado') {
                    throw ValidationException::withMessages(['scope' => 'Solo se puede cotizar una solicitud aprobada.']);
                }
                if ($existing = $locked->quotation()->first()) {
                    return $existing;
                }
                $amount = BigDecimal::of((string) $data['official_amount'])->toScale(2, RoundingMode::HalfUp);
                if (! $amount->isPositive()) {
                    throw ValidationException::withMessages(['official_amount' => 'El importe oficial debe ser positivo.']);
                }
                $code = 'COT-'.Str::upper((string) Str::ulid());
                $path = 'quotations/'.$code.'.pdf';
                $quotation = $locked->quotation()->create([
                    'code' => $code, 'user_id' => $user->id, 'scope' => $data['scope'], 'official_amount' => (string) $amount, 'currency' => 'PEN',
                    'client_snapshot' => ['name' => $locked->client_name, 'email' => $locked->client_email, 'phone' => $locked->client_phone, 'city' => $locked->city, 'request_code' => $locked->code, 'service' => $locked->service, 'project_type' => $locked->project_type],
                    'studio_snapshot' => config('commercial.studio'), 'issued_at' => today(), 'expires_at' => today()->addDays(15), 'pdf_path' => $path,
                ]);
                $content = $this->pdf->render($quotation);
                if (! str_starts_with($content, '%PDF-') || ! Storage::disk('commercial')->put($path, $content)) {
                    throw new \RuntimeException('No se pudo generar o guardar el PDF.');
                }
                $locked->histories()->create(['user_id' => $user->id, 'action' => 'emision_cotizacion', 'previous_status' => 'aprobado', 'new_status' => 'aprobado', 'observation' => 'Cotización oficial '.$code.' emitida.']);

                return $quotation;
            });
        } catch (\Throwable $e) {
            if ($path) {
                try {
                    Storage::disk('commercial')->delete($path);
                } catch (\Throwable $cleanup) {
                    report($cleanup);
                }
            }
            throw $e;
        }
    }
}

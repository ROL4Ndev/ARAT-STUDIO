<?php

namespace App\Services;

use App\Exceptions\EstimateChanged;
use App\Models\OfferedService;
use App\Models\ServiceRequest;
use App\Models\TariffGroup;
use Brick\Math\BigDecimal;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ServiceRequestService
{
    public function __construct(private QuotationCalculatorService $calculator, private AuditService $audit, private NotificationService $notifications) {}

    public function register(array $data, ?UploadedFile $file, ?int $relatedId = null): array
    {
        $fields = Arr::only($data, ['client_name', 'client_email', 'client_phone', 'city', 'service_id', 'project_type', 'description', 'area_m2', 'lod_selected', 'external_url']);
        foreach (['area_m2', 'lod_selected', 'external_url'] as $key) {
            $fields[$key] = $fields[$key] ?? null;
        }
        if ($fields['area_m2'] !== null) {
            $fields['area_m2'] = (string) BigDecimal::of((string) $fields['area_m2'])->toScale(2);
        }
        $fields['service_id'] = (int) $fields['service_id'];
        $fields['related_request_id'] = $relatedId;
        ksort($fields);
        $hash = hash('sha256', json_encode([$fields, $file ? hash_file('sha256', $file->getRealPath()) : null]));
        if ($existing = ServiceRequest::where('submission_token', $data['submission_token'])->first()) {
            abort_unless($existing->request_hash && hash_equals($existing->request_hash, $hash), 409, 'Token ya utilizado con contenido diferente.');

            return [$existing, false];
        }
        $path = null;
        try {
            if ($file) {
                $path = 'attachments/'.Str::uuid().'.'.strtolower($file->getClientOriginalExtension());
                if (! Storage::disk('commercial')->putFileAs('attachments', $file, basename($path))) {
                    throw new \RuntimeException('No se pudo guardar el adjunto.');
                }
            }
            $record = DB::transaction(function () use ($data, $fields, $hash, $path, $file) {
                $service = OfferedService::whereKey($fields['service_id'])->lockForUpdate()->firstOrFail();
                abort_unless($service->is_active, 422, 'El servicio no admite nuevas solicitudes.');
                if ($fields['lod_selected']) {
                    TariffGroup::where('lod_level', $fields['lod_selected'])->lockForUpdate()->first();
                }
                $e = $this->calculator->calculate($fields['area_m2'], $fields['lod_selected'], $service);
                if (! isset($data['estimate_token']) || ! hash_equals($e['estimate_token'], $data['estimate_token'])) {
                    throw new EstimateChanged($e);
                }
                $record = ServiceRequest::create($fields + ['submission_token' => $data['submission_token'], 'request_hash' => $hash, 'code' => 'SOL-'.Str::ulid(), 'service' => $service->name, 'service_snapshot' => $e['service_snapshot'], 'tariff_snapshot' => $e['tariff_snapshot'], 'tariff_param_id' => $e['tariff_snapshot']['id'], 'estimated_cost' => $e['estimated_cost_decimal'], 'currency' => 'PEN', 'custom_evaluation' => $e['custom_evaluation'], 'estimation_reason' => $e['reason'], 'base_price_m2' => $e['base_price_m2'], 'lod_factor' => $e['lod_factor'], 'max_area_m2' => $e['max_area_m2'], 'attachment_path' => $path, 'attachment_name' => $file ? mb_substr(basename($file->getClientOriginalName()), 0, 240) : null, 'attachment_mime' => $file?->getMimeType(), 'status' => 'pendiente', 'consented_at' => now('UTC')]);
                $revision = $record->revisions()->create(['version' => 1, 'author_type' => 'public', 'kind' => 'initial', 'after' => Arr::except($record->toArray(), ['submission_token', 'request_hash']), 'created_at' => now('UTC')]);
                $record->update(['current_revision_id' => $revision->id]);
                if ($path) {
                    $record->files()->create(['request_revision_id' => $revision->id, 'path' => $path, 'original_name' => $record->attachment_name, 'mime' => $record->attachment_mime, 'size' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath()), 'created_at' => now('UTC')]);
                }
                $record->histories()->create(['action' => 'creacion', 'new_status' => 'pendiente', 'is_public' => true, 'observation' => 'Solicitud recibida con consentimiento.']);
                $this->audit->record($record, 'request_created', 'Registro público', [], Arr::except($record->toArray(), ['submission_token', 'request_hash']));
                $this->notifications->queue('received', $record->id, $record->client_email);

                return $record;
            });

            return [$record, true];
        } catch (\Throwable $ex) {
            if ($path) {
                try {
                    Storage::disk('commercial')->delete($path);
                } catch (\Throwable $cleanup) {
                    report($cleanup);
                }
            }
            if ($ex instanceof UniqueConstraintViolationException && ($existing = ServiceRequest::where('submission_token', $data['submission_token'])->first())) {
                abort_unless(hash_equals($existing->request_hash, $hash), 409, 'El token ya se utilizó con otro contenido.');

                return [$existing, false];
            }
            throw $ex;
        }
    }
}

<?php

namespace App\Services;

use App\Models\Quotation;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\WorkIntent;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IssueQuotationService
{
    public function __construct(private AuditService $audit) {}

    public function draft(ServiceRequest $record, User $actor, array $data, ?Quotation $source = null): Quotation
    {
        return DB::transaction(function () use ($record, $actor, $data, $source) {
            $r = ServiceRequest::whereKey($record->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('operate', $r);
            abort_unless($r->status === 'aprobado', 409, 'Solo se cotiza una solicitud aprobada.');
            if ($pending = $r->quotations()->whereIn('status', ['borrador', 'preparando'])->first()) {
                return $pending;
            }
            abort_unless($r->lock_version === (int) $data['request_lock_version'], 409, 'El expediente cambió antes de crear el borrador.');
            if ($source) {
                $source = Quotation::whereKey($source->id)->lockForUpdate()->firstOrFail();
                abort_unless($source->service_request_id === $r->id && in_array($source->status, ['emitida', 'rechazada', 'vencida']), 409, 'La versión no admite revisión.');
            }
            $this->validateAmount($r, $data);
            $q = $r->quotations()->create(Arr::only($data, ['scope', 'deliverables', 'exclusions', 'execution_term', 'adjustment_reason']) + ['code' => 'COT-'.Str::ulid(), 'version' => (int) $r->quotations()->max('version') + 1, 'user_id' => $actor->id, 'official_amount' => (string) BigDecimal::of((string) $data['official_amount'])->toScale(2, RoundingMode::HalfUp), 'currency' => 'PEN', 'status' => 'borrador', 'pdf_status' => 'pending', 'notification_status' => 'pending', 'request_revision_id' => $r->current_revision_id, 'version_reason' => $data['reason'], 'client_snapshot' => [], 'studio_snapshot' => []]);
            $r->increment('lock_version');
            $this->audit->record($q, 'quotation_draft', $data['reason'], [], $q->toArray(), $actor);

            return $q;
        }, 3);
    }

    public function edit(Quotation $record, User $actor, array $data): Quotation
    {
        return DB::transaction(function () use ($record, $actor, $data) {
            $r = ServiceRequest::whereKey($record->service_request_id)->lockForUpdate()->firstOrFail();
            $q = DomainLock::get($record, (int) $data['lock_version']);
            Gate::forUser($actor)->authorize('update', $q);
            abort_unless($r->status === 'aprobado', 409);
            $this->validateAmount($r, $data);
            $before = $q->toArray();
            $q->fill(Arr::only($data, ['scope', 'deliverables', 'exclusions', 'execution_term', 'adjustment_reason']));
            $q->official_amount = (string) BigDecimal::of((string) $data['official_amount'])->toScale(2, RoundingMode::HalfUp);
            $q->lock_version++;
            $q->save();
            $this->audit->record($q, 'quotation_edit', $data['reason'], $before, $q->toArray(), $actor);

            return $q;
        }, 3);
    }

    public function prepare(Quotation $record, User $actor, int $version): WorkIntent
    {
        return DB::transaction(function () use ($record, $actor, $version) {
            $r = ServiceRequest::whereKey($record->service_request_id)->lockForUpdate()->firstOrFail();
            $q = Quotation::whereKey($record->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('view', $q);
            abort_unless($r->status === 'aprobado', 409, 'El expediente ya no permite emitir.');
            if ($q->status === 'preparando') {
                $intent = WorkIntent::where('key', 'pdf:'.$q->id)->lockForUpdate()->firstOrFail();
                if ($intent->status === 'failed') {
                    abort_unless($q->lock_version === $version, 409, 'La versión cambió antes del reintento.');
                    $before = $q->toArray();
                    $intent->update(['status' => 'pending', 'lease' => null, 'leased_at' => null]);
                    $q->update(['pdf_status' => 'pending', 'technical_error' => null, 'lock_version' => $q->lock_version + 1]);
                    $this->audit->record($q, 'pdf_retry', 'Reintento de la misma versión', $before, $q->toArray(), $actor);
                }

                return $intent;
            }
            if ($q->status === 'emitida') {
                return WorkIntent::firstOrCreate(['key' => 'pdf:'.$q->id], ['kind' => 'pdf', 'resource_id' => $q->id, 'status' => 'done', 'result' => ['legacy' => true, 'generated_path' => $q->pdf_path, 'sha256' => $q->pdf_hash]]);
            }
            abort_unless($q->status === 'borrador' && $q->lock_version === $version, 409, 'La versión cambió o ya no es borrador.');
            $this->validateAmount($r, $q->toArray());
            $before = $q->toArray();
            $q->fill(['user_id' => $actor->id, 'request_revision_id' => $r->current_revision_id, 'client_snapshot' => ['name' => $r->client_name, 'email' => $r->client_email, 'phone' => $r->client_phone, 'city' => $r->city, 'request_code' => $r->code, 'service' => $r->service, 'project_type' => $r->project_type], 'studio_snapshot' => config('commercial.studio'), 'price_snapshot' => ['service' => $r->service_snapshot, 'tariff' => $r->tariff_snapshot, 'estimate' => $r->estimated_cost, 'currency' => 'PEN'], 'status' => 'preparando', 'pdf_status' => 'pending', 'technical_error' => null, 'lock_version' => $q->lock_version + 1]);
            $q->save();
            $r->increment('lock_version');
            $intent = WorkIntent::firstOrCreate(['key' => 'pdf:'.$q->id], ['kind' => 'pdf', 'resource_id' => $q->id, 'status' => 'pending']);
            $this->audit->record($q, 'quotation_preparing', 'Emisión solicitada', $before, $q->toArray(), $actor);

            return $intent;
        }, 3);
    }

    private function validateAmount(ServiceRequest $r, array $data): void
    {
        $amount = BigDecimal::of((string) $data['official_amount']);
        if (! $amount->isPositive()) {
            throw ValidationException::withMessages(['official_amount' => 'El importe debe ser positivo.']);
        }
        if (($r->estimated_cost === null || ! $amount->isEqualTo($r->estimated_cost)) && mb_strlen(trim($data['adjustment_reason'] ?? '')) < 5) {
            throw ValidationException::withMessages(['adjustment_reason' => 'Explica el ajuste del importe o la evaluación manual.']);
        }
    }
}

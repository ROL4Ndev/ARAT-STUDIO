<?php

namespace App\Services;

use App\Models\ClientGrant;
use App\Models\Quotation;
use App\Models\ServiceRequest;
use App\Models\WorkIntent;
use App\Policies\ServiceRequestPolicy;
use Illuminate\Support\Facades\DB;

class ClientResponseService
{
    public function __construct(private AuditService $audit) {}

    public function respond(Quotation $record, ClientGrant $grant, array $data): Quotation
    {
        return DB::transaction(function () use ($record, $grant, $data) {
            $r = ServiceRequest::whereKey($record->service_request_id)->lockForUpdate()->firstOrFail();
            $q = Quotation::whereKey($record->id)->lockForUpdate()->firstOrFail();
            abort_unless(app(ServiceRequestPolicy::class)->clientView($grant, $r), 403);
            $decision = $data['decision'];
            if (in_array($q->status, ['aceptada', 'rechazada'])) {
                abort_unless($q->status === $decision, 409, 'Ya existe una respuesta diferente.');
                abort_unless($r->current_quotation_id === $q->id, 409, 'Esta versión ya no es la propuesta actual.');

                return $q;
            }
            abort_unless($r->status === 'aprobado' && $r->current_quotation_id === $q->id && $q->status === 'emitida' && $q->pdf_status === 'ready' && ! $q->isExpired() && $q->lock_version === (int) $data['lock_version'], 409, 'La propuesta cambió, venció o no puede responderse.');
            abort_if($r->quotations()->where('status', 'aceptada')->exists(), 409, 'Ya existe una aceptación.');
            if ($decision === 'rechazada') {
                abort_if(trim($data['reason'] ?? '') === '', 422, 'Indica el motivo del rechazo.');
            }
            $before = $q->toArray();
            $q->update(['status' => $decision, 'response_reason' => $data['reason'] ?? null, 'responded_at' => now('UTC'), 'lock_version' => $q->lock_version + 1]);
            if ($decision === 'aceptada') {
                foreach ($r->quotations()->where('id', '<>', $q->id)->whereIn('status', ['borrador', 'preparando', 'emitida'])->orderBy('id')->lockForUpdate()->get() as $other) {
                    $old = $other->toArray();
                    $other->update(['status' => 'anulada', 'lock_version' => $other->lock_version + 1]);
                    WorkIntent::where('key', 'pdf:'.$other->id)->whereIn('status', ['pending', 'processing', 'failed'])->update(['status' => 'cancelled']);
                    $this->audit->record($other, 'annulled', 'Se aceptó otra versión', $old, $other->toArray(), $grant);
                }
                $r->update(['status' => 'cerrada', 'closure_outcome' => 'aceptada', 'closed_at' => now('UTC'), 'closure_reason' => 'Aceptación verificada de '.$q->code, 'lock_version' => $r->lock_version + 1]);
            } else {
                $r->increment('lock_version');
            }
            $r->histories()->create(['action' => 'client_response', 'client_grant_id' => $grant->id, 'quotation_id' => $q->id, 'metadata' => ['lock_version' => $q->lock_version], 'previous_status' => 'aprobado', 'new_status' => $r->status, 'is_public' => true, 'observation' => 'Cotización '.$q->code.' '.$decision.'. '.($data['reason'] ?? '')]);
            $this->audit->record($q, 'client_response', $decision, $before, $q->toArray(), $grant);

            return $q;
        }, 3);
    }
}

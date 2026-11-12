<?php

namespace App\Services;

use App\Models\ClientGrant;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\WorkIntent;
use App\Policies\ServiceRequestPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CommercialWorkflowService
{
    public function __construct(private AuditService $audit, private NotificationService $notifications) {}

    public function transition(ServiceRequest $record, User $actor, array $data): void
    {
        DB::transaction(function () use ($record, $actor, $data) {
            $r = DomainLock::get($record, (int) $data['lock_version']);
            $action = $data['action'];
            $before = $r->toArray();
            $reason = trim($data['reason']);
            abort_if($reason === '', 422, 'Indica el motivo.');
            if ($action === 'claim' || $action === 'assign') {
                Gate::forUser($actor)->authorize($action, $r);
                abort_if(in_array($r->status, ['rechazado', 'cancelado', 'cerrada']), 409, 'El expediente está finalizado.');
                $responsible = $action === 'claim' ? $actor : User::whereKey($data['assigned_to'])->where('is_active', true)->whereIn('role', ['admin', 'gestor'])->firstOrFail();
                $r->assigned_to = $responsible->id;
            } else {
                Gate::forUser($actor)->authorize('operate', $r);
                $next = match ($action) {
                    'start' => 'evaluacion','observe' => 'observada','resume' => 'evaluacion','approve' => 'aprobado','reject' => 'rechazado','cancel' => 'cancelado','close' => 'cerrada'
                };
                $allowed = match ($action) {
                    'start' => ['pendiente'],'observe','approve','reject' => ['evaluacion'],'resume' => ['observada'],'close' => ['aprobado'],'cancel' => ['pendiente', 'evaluacion', 'observada', 'aprobado']
                };
                abort_unless(in_array($r->status, $allowed, true), 409, 'Esta acción no corresponde al estado actual.');
                if ($action === 'start') {
                    abort_unless($r->assigned_to, 409, 'Asigna un responsable antes de evaluar.');
                    $r->first_evaluated_at ??= now('UTC');
                }
                if ($action === 'observe') {
                    if (empty($data['public_observation'])) {
                        throw ValidationException::withMessages(['public_observation' => 'La observación pública es obligatoria.']);
                    }$r->observed_revision_id = $r->current_revision_id;
                    $this->notifications->queue('observed', $r->current_revision_id, $r->client_email);
                }
                if ($action === 'resume') {
                    abort_unless($r->revisions()->where('kind', 'correction')->where('id', '>', $r->observed_revision_id ?? 0)->exists(), 409, 'Aún no hay una corrección válida del cliente.');
                }
                if ($action === 'approve') {
                    if (mb_strlen(trim($data['assessment'] ?? '')) < 20) {
                        throw ValidationException::withMessages(['assessment' => 'Registra una evaluación técnica de al menos 20 caracteres.']);
                    }
                    $r->evaluations()->create(['request_revision_id' => $r->current_revision_id, 'user_id' => $actor->id, 'assessment' => $data['assessment'], 'created_at' => now('UTC')]);
                }
                if ($action === 'reject') {
                    $r->rejection_reason = $reason;
                    $r->closed_at = now('UTC');
                    $r->closure_reason = $reason;
                    $r->closure_outcome = 'rechazado';
                }
                if ($action === 'cancel') {
                    $this->cancelProposals($r, $actor, $reason);
                }
                if ($action === 'close') {
                    abort_if($r->quotations()->whereIn('status', ['borrador', 'preparando', 'aceptada'])->exists() || $r->quotations()->where('status', 'emitida')->where('expires_at', '>', now('UTC'))->exists(), 409, 'No se puede cerrar con propuesta vigente, preparación o aceptación.');
                    $r->closure_outcome = 'sin_acuerdo';
                }
                if (in_array($action, ['cancel', 'close'])) {
                    $r->closed_at = now('UTC');
                    $r->closure_reason = $reason;
                    $r->closure_outcome = $action === 'cancel' ? 'cancelado' : 'sin_acuerdo';
                }
                $r->status = $next;
            }
            $r->lock_version++;
            $r->save();
            $public = in_array($action, ['observe', 'reject', 'start', 'resume', 'approve', 'cancel', 'close']);
            $observation = $action === 'observe' ? $data['public_observation'] : ($action === 'reject' ? $reason : ($public ? 'Estado actualizado a '.$r->status.'.' : $reason));
            $r->histories()->create(['user_id' => $actor->id, 'action' => $action, 'previous_status' => $before['status'], 'new_status' => $r->status, 'observation' => $observation, 'is_public' => $public]);
            $this->audit->record($r, $action, $reason, $before, $r->toArray(), $actor);
        }, 3);
    }

    public function cancel(ServiceRequest $record, User|ClientGrant $actor, int $version, string $reason): void
    {
        DB::transaction(function () use ($record, $actor, $version, $reason) {
            $r = DomainLock::get($record, $version);
            if ($actor instanceof ClientGrant) {
                abort_unless(app(ServiceRequestPolicy::class)->clientView($actor, $r), 403);
            } else {
                Gate::forUser($actor)->authorize('operate', $r);
            }
            abort_unless(in_array($r->status, ['pendiente', 'evaluacion', 'observada', 'aprobado']), 409, 'La solicitud ya está finalizada.');
            $before = $r->toArray();
            $this->cancelProposals($r, $actor, $reason);
            $r->update(['status' => 'cancelado', 'closure_outcome' => 'cancelado', 'closed_at' => now('UTC'), 'closure_reason' => $reason, 'lock_version' => $r->lock_version + 1]);
            $r->histories()->create(['user_id' => $actor instanceof User ? $actor->id : null, 'action' => 'cancel', 'previous_status' => $before['status'], 'new_status' => 'cancelado', 'is_public' => true, 'observation' => 'Solicitud cancelada: '.$reason]);
            $this->audit->record($r, 'cancel', $reason, $before, $r->toArray(), $actor);
        }, 3);
    }

    private function cancelProposals(ServiceRequest $r, User|ClientGrant $actor, string $reason): void
    {
        abort_if($r->quotations()->where('status', 'aceptada')->exists(), 409, 'Una aceptación impide cancelar.');
        foreach ($r->quotations()->whereIn('status', ['borrador', 'preparando', 'emitida'])->orderBy('id')->lockForUpdate()->get() as $q) {
            $before = $q->toArray();
            $q->update(['status' => 'anulada', 'lock_version' => $q->lock_version + 1]);
            WorkIntent::where('key', 'pdf:'.$q->id)->whereIn('status', ['pending', 'processing', 'failed'])->update(['status' => 'cancelled']);
            $this->audit->record($q, 'annulled', $reason, $before, $q->toArray(), $actor);
        }
    }
}

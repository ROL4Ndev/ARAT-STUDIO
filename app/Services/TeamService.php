<?php

namespace App\Services;

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TeamService
{
    public function __construct(private AuditService $audit, private CommercialWorkflowService $workflow) {}

    public function save(?User $record, User $actor, array $data): User
    {
        return DB::transaction(function () use ($record, $actor, $data) {
            $manager = $record ? DomainLock::get($record, (int) $data['lock_version']) : new User;
            Gate::forUser($actor)->authorize($record ? 'update' : 'create', $record ? $manager : User::class);
            $before = $this->snapshot($manager);
            $manager->name = $data['name'];
            $manager->email = mb_strtolower(trim($data['email']));
            if (! $record) {
                $manager->role = 'gestor';
                $manager->is_active = true;
                $manager->password = $data['password'];
            }
            $manager->lock_version++;
            $manager->save();
            $this->audit->record($manager, $record ? 'manager_updated' : 'manager_created', $data['reason'], $before, $this->snapshot($manager), $actor);

            return $manager;
        }, 3);
    }

    public function changeStatus(User $record, User $actor, array $data): void
    {
        DB::transaction(function () use ($record, $actor, $data) {
            // El mismo orden de usuarios antes de expedientes se usa al asignar en el flujo comercial.
            $ids = array_filter([$actor->id, $record->id, $data['replacement_id'] ?? null]);
            $users = User::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $manager = $users[$record->id];
            Gate::forUser($users[$actor->id])->authorize('update', $manager);
            abort_unless($manager->lock_version === (int) $data['lock_version'], 409, 'La cuenta cambió. Recarga antes de continuar.');
            $before = $this->snapshot($manager);
            $active = (bool) $data['is_active'];
            if (! $active) {
                $requests = ServiceRequest::where('assigned_to', $manager->id)
                    ->whereNotIn('status', ['rechazado', 'cancelado', 'cerrada'])->orderBy('id')->lockForUpdate()->get();
                abort_unless($requests->count() === (int) $data['expected_open_count'], 409, 'Cambió la cantidad de expedientes abiertos. Revisa la cuenta antes de desactivarla.');
                if ($requests->isNotEmpty()) {
                    $replacement = $users[$data['replacement_id'] ?? 0] ?? null;
                    if (! $replacement || $replacement->id === $manager->id || ! $replacement->is_active || ! in_array($replacement->role, ['admin', 'gestor'], true)) {
                        throw ValidationException::withMessages(['replacement_id' => 'Elige otro responsable activo para los expedientes abiertos.']);
                    }
                    foreach ($requests as $request) {
                        $this->workflow->transition($request, $actor, [
                            'action' => 'assign', 'assigned_to' => $replacement->id,
                            'lock_version' => $request->lock_version, 'reason' => $data['reason'],
                        ]);
                    }
                }
            }
            $manager->is_active = $active;
            $manager->auth_version++;
            $manager->lock_version++;
            $manager->remember_token = null;
            $manager->save();
            $this->audit->record($manager, $active ? 'manager_activated' : 'manager_deactivated', $data['reason'], $before, $this->snapshot($manager), $actor);
        }, 3);
    }

    public function resetPassword(User $record, User $actor, array $data): void
    {
        DB::transaction(function () use ($record, $actor, $data) {
            $manager = DomainLock::get($record, (int) $data['lock_version']);
            Gate::forUser($actor)->authorize('update', $manager);
            $before = $this->snapshot($manager);
            $manager->password = $data['password'];
            $manager->auth_version++;
            $manager->lock_version++;
            $manager->remember_token = null;
            $manager->save();
            $this->audit->record($manager, 'manager_access_reset', $data['reason'], $before, $this->snapshot($manager), $actor);
        }, 3);
    }

    private function snapshot(User $user): array
    {
        return $user->exists ? $user->only(['name', 'email', 'role', 'is_active', 'lock_version']) : [];
    }
}

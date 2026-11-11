<?php

namespace App\Services;

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommercialWorkflowService
{
    public function transition(ServiceRequest $request, User $user, array $data): void
    {
        abort_unless(in_array($user->role, ['admin', 'gestor'], true), 403);
        DB::transaction(function () use ($request, $user, $data) {
            $locked = ServiceRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $allowed = ['pendiente' => ['evaluacion'], 'evaluacion' => ['aprobado', 'rechazado']];
            if ($locked->status !== $data['expected_status'] || ! in_array($data['status'], $allowed[$locked->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'La transición no está permitida o la solicitud fue actualizada. Recarga el detalle.']);
            }
            $observation = trim($data['observation'] ?? '');
            if ($data['status'] === 'rechazado' && $observation === '') {
                throw ValidationException::withMessages(['observation' => 'Indica el motivo del rechazo.']);
            }
            $previous = $locked->status;
            $locked->update(['status' => $data['status'], 'rejection_reason' => $data['status'] === 'rechazado' ? $observation : null]);
            $locked->histories()->create(['user_id' => $user->id, 'action' => 'cambio_estado', 'previous_status' => $previous, 'new_status' => $locked->status, 'observation' => $observation ?: null]);
        }, 3);
    }
}

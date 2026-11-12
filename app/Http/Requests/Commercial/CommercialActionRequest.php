<?php

namespace App\Http\Requests\Commercial;

use Illuminate\Validation\Rule;

class CommercialActionRequest extends CommercialRequest
{
    public function rules(): array
    {
        return ['action' => ['required', Rule::in(['claim', 'assign', 'start', 'observe', 'resume', 'approve', 'reject', 'cancel', 'close'])], 'lock_version' => ['required', 'integer', 'min:0'], 'reason' => ['required', 'string', 'min:5', 'max:5000'], 'assigned_to' => ['required_if:action,assign', 'nullable', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('is_active', true)->whereIn('role', ['admin', 'gestor']))], 'assessment' => ['required_if:action,approve', 'nullable', 'string', 'min:20', 'max:10000'], 'public_observation' => ['required_if:action,observe', 'nullable', 'string', 'min:10', 'max:5000']];
    }

    public function attributes(): array
    {
        return ['reason' => 'motivo', 'assessment' => 'evaluación técnica', 'public_observation' => 'observación pública', 'assigned_to' => 'responsable', 'lock_version' => 'versión de edición'];
    }
}

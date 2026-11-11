<?php

namespace App\Http\Requests\Commercial;

use Illuminate\Validation\Rule;

class TransitionServiceRequest extends CommercialRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['evaluacion', 'aprobado', 'rechazado'])],
            'expected_status' => ['required', Rule::in(['pendiente', 'evaluacion'])],
            'observation' => ['required_if:status,rechazado', 'nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return ['status' => 'estado', 'expected_status' => 'estado anterior', 'observation' => 'motivo u observación'];
    }
}

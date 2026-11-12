<?php

namespace App\Http\Requests\Commercial;

use Illuminate\Validation\Rule;

class FilterServiceRequests extends CommercialRequest
{
    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:150'], 'service_id' => ['nullable', 'integer', 'exists:services,id'], 'history_action' => ['nullable', 'string', 'max:60'], 'status' => ['nullable', Rule::in(['pendiente', 'evaluacion', 'observada', 'aprobado', 'rechazado', 'cancelado', 'cerrada'])], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']];
    }
}

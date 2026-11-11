<?php

namespace App\Http\Requests\Commercial;

use Illuminate\Validation\Rule;

class FilterServiceRequests extends CommercialRequest
{
    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:150'], 'status' => ['nullable', Rule::in(['pendiente', 'evaluacion', 'aprobado', 'rechazado'])]];
    }

    public function attributes(): array
    {
        return ['search' => 'búsqueda', 'status' => 'estado'];
    }
}

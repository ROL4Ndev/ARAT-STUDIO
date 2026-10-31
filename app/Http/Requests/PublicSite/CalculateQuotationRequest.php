<?php

namespace App\Http\Requests\PublicSite;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalculateQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'area_m2' => [
                'required',
                'numeric',
                'min:0.01',
                'max:1000000',
                'decimal:0,2',
            ],
            'lod_selected' => [
                'required',
                Rule::in(['LOD_200', 'LOD_300', 'LOD_400']),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'area_m2.required' => 'Ingresa el área del proyecto.',
            'area_m2.numeric' => 'El área debe ser un número.',
            'area_m2.min' => 'El área debe ser mayor que cero.',
            'area_m2.max' => 'El área supera el límite de entrada permitido.',
            'area_m2.decimal' => 'El área admite hasta dos decimales.',
            'lod_selected.required' => 'Selecciona un nivel de desarrollo.',
            'lod_selected.in' => 'El nivel seleccionado no es válido.',
        ];
    }
}

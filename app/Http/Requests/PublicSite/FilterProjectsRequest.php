<?php

namespace App\Http\Requests\PublicSite;

use Illuminate\Foundation\Http\FormRequest;

class FilterProjectsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => [
                'nullable',
                'string',
                'max:255',
                'exists:categories,slug',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'category.string' => 'La categoría no es válida.',
            'category.max' => 'La categoría no es válida.',
            'category.exists' => 'La categoría seleccionada no existe.',
        ];
    }
}
<?php

namespace App\Http\Requests\Admin;

use App\Services\CatalogRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatalogFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        [, $class] = CatalogRegistry::get($this->route('catalog_kind'));

        return $this->user()?->can('viewAny', $class) === true;
    }

    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:150'], 'version' => ['nullable', 'integer', 'min:1'], 'active' => ['nullable', Rule::in(['0', '1'])], 'status' => ['nullable', Rule::in(['borrador', 'activa', 'inactiva'])], 'audience' => ['nullable', Rule::in(['general', 'estudiantes'])], 'lod' => ['nullable', Rule::in(['LOD_200', 'LOD_300', 'LOD_400'])], 'category' => ['nullable', 'integer', 'exists:categories,id']];
    }
}

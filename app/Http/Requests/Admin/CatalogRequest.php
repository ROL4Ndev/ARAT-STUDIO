<?php

namespace App\Http\Requests\Admin;

use App\Rules\ProjectImage;
use App\Services\CatalogRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        [, $class] = CatalogRegistry::get($this->route('catalog_kind'));
        $record = $this->route('record');

        return $record ? $this->user()?->can('update', $class::findOrFail($record)) === true : $this->user()?->can('create', $class) === true;
    }

    public function rules(): array
    {
        $kind = $this->route('catalog_kind');
        $id = $this->route('record');
        $rules = ['reason' => ['required', 'string', 'min:5', 'max:2000'], 'lock_version' => [$id ? 'required' : 'nullable', 'integer', 'min:0']];
        if (str_ends_with($this->route()->getName(), '.status')) {
            return $rules + ['active' => ['required', 'boolean']] + ($kind === 'tariffs' ? ['expected_active_id' => ['present', 'nullable', 'integer', 'exists:tariff_params,id']] : []);
        }

        return $rules + match ($kind) {
            'categories' => ['name' => ['required', 'string', 'min:2', 'max:100', Rule::unique('categories')->ignore($id)], 'description' => ['nullable', 'string', 'max:5000']],
            'projects' => ['title' => ['required', 'string', 'min:3', 'max:180'], 'description' => ['required', 'string', 'min:20', 'max:10000'], 'category_id' => ['required', 'integer', Rule::exists('categories', 'id')], 'cover_image' => [$id ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', new ProjectImage], 'is_active' => ['required', 'boolean']],
            'services' => ['code' => [$id ? 'prohibited' : 'required', 'string', 'regex:/^[A-Z0-9_-]{2,60}$/', Rule::unique('services')], 'name' => ['required', 'string', 'min:3', 'max:150'], 'description' => ['required', 'string', 'min:10', 'max:5000'], 'audience' => ['required', Rule::in(['general', 'estudiantes'])], 'display_order' => ['required', 'integer', 'min:0', 'max:100000'], 'estimation_mode' => ['required', Rule::in(['area_lod', 'manual'])]],
            'tariffs' => ['lod_level' => [$id ? 'prohibited' : 'required', Rule::in(['LOD_200', 'LOD_300', 'LOD_400'])], 'base_price_m2' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99', 'decimal:0,2'], 'lod_factor' => ['required', 'numeric', 'min:0.001', 'max:99999.999', 'decimal:0,3'], 'max_area_m2' => ['required', 'numeric', 'min:0.01', 'max:1000000', 'decimal:0,2']],
        };
    }

    public function attributes(): array
    {
        return ['name' => 'nombre', 'title' => 'título', 'description' => 'descripción', 'reason' => 'motivo', 'category_id' => 'categoría', 'cover_image' => 'portada', 'lock_version' => 'versión de edición', 'base_price_m2' => 'tarifa base', 'lod_factor' => 'factor LOD', 'max_area_m2' => 'límite de área', 'code' => 'código', 'audience' => 'público objetivo', 'display_order' => 'orden', 'estimation_mode' => 'modalidad'];
    }
}

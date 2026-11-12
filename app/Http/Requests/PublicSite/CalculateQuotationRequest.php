<?php

namespace App\Http\Requests\PublicSite;

use App\Models\OfferedService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalculateQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('service_id') && $this->filled('service')) {
            $this->merge(['service_id' => OfferedService::where('name', $this->input('service'))->value('id')]);
        }
    }

    public function rules(): array
    {
        $manual = OfferedService::find($this->input('service_id'))?->estimation_mode === 'manual';

        return ['service_id' => ['nullable', 'integer', 'exists:services,id'], 'area_m2' => [$manual ? 'nullable' : 'required', 'numeric', 'min:0.01', 'max:1000000', 'decimal:0,2'], 'lod_selected' => [$manual ? 'nullable' : 'required', Rule::in(['LOD_200', 'LOD_300', 'LOD_400'])]];
    }

    public function attributes(): array
    {
        return ['area_m2' => 'área', 'lod_selected' => 'nivel LOD', 'service_id' => 'servicio'];
    }
}

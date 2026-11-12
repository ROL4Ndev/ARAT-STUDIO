<?php

namespace App\Http\Requests\Client;

use App\Rules\ProjectAttachment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->has('clientGrant') || ($this->route('serviceRequest') && $this->user()?->can('operate', $this->route('serviceRequest')));
    }

    public function rules(): array
    {
        $staff = $this->route('serviceRequest') !== null;
        $record = $staff ? $this->route('serviceRequest') : $this->attributes->get('clientGrant')->serviceRequest;
        $manual = $record->service_snapshot['estimation_mode'] === 'manual';

        return ['lock_version' => ['required', 'integer', 'min:0'], 'client_name' => [$staff ? 'prohibited' : 'required', 'string', 'max:150'], 'client_phone' => [$staff ? 'prohibited' : 'required', 'string', 'regex:/^[+()0-9\s-]{6,30}$/'], 'city' => [$staff ? 'prohibited' : 'required', 'string', 'max:120'], 'project_type' => ['required', Rule::in(['Vivienda', 'Comercio', 'Oficina', 'Otro'])], 'description' => ['required', 'string', 'min:10', 'max:10000'], 'area_m2' => [$manual ? 'nullable' : 'required', 'numeric', 'min:0.01', 'max:1000000', 'decimal:0,2'], 'lod_selected' => [$manual ? 'nullable' : 'required', Rule::in(['LOD_200', 'LOD_300', 'LOD_400'])], 'external_url' => ['nullable', 'url:http,https', 'max:2048'], 'attachment' => ['bail', 'nullable', 'file', 'max:51200', new ProjectAttachment], 'evidence' => [$staff ? 'required' : 'prohibited', 'string', 'min:20', 'max:5000'], 'reason' => ['required', 'string', 'min:5', 'max:5000'], 'service_id' => ['prohibited'], 'service' => ['prohibited'], 'client_email' => ['prohibited']];
    }

    public function messages(): array
    {
        return ['attachment.uploaded' => 'No se pudo recibir el archivo. Revisa que no supere 50 MiB o utiliza un enlace externo.', 'attachment.max' => 'El archivo supera 50 MiB. Utiliza un enlace externo.'];
    }

    public function attributes(): array
    {
        return ['reason' => 'explicación de la corrección', 'attachment' => 'archivo', 'client_name' => 'nombre', 'client_phone' => 'teléfono', 'city' => 'ciudad', 'description' => 'descripción'];
    }
}

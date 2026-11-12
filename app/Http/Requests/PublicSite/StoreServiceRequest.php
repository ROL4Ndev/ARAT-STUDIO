<?php

namespace App\Http\Requests\PublicSite;

use App\Rules\ProjectAttachment;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends CalculateQuotationRequest
{
    public const PROJECT_TYPES = ['Vivienda', 'Comercio', 'Oficina', 'Otro'];

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'related' => ['nullable', 'boolean'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'submission_token' => ['required', 'uuid', function ($a, $v, $fail) {
                if (! in_array($v, $this->session()->get('project_submission_tokens', []), true)) {
                    $fail('El formulario venció. Abre nuevamente Solicitar proyecto.');
                }
            }],
            'client_name' => ['required', 'string', 'max:150'], 'client_email' => ['required', 'email', 'max:255'], 'client_phone' => ['required', 'string', 'regex:/^[+()0-9\s-]{6,30}$/'], 'city' => ['required', 'string', 'max:120'], 'project_type' => ['required', Rule::in(self::PROJECT_TYPES)], 'description' => ['required', 'string', 'min:10', 'max:10000'], 'consent' => ['required', 'accepted'], 'attachment' => ['bail', 'nullable', 'file', 'max:51200', new ProjectAttachment], 'external_url' => ['nullable', 'url:http,https', 'max:2048'], 'estimate_token' => ['nullable', 'string', 'size:64'], 'estimate_confirmed' => ['required', 'accepted']]);
    }

    public function attributes(): array
    {
        return parent::attributes() + ['client_name' => 'nombre', 'client_email' => 'correo', 'client_phone' => 'teléfono', 'city' => 'ciudad', 'project_type' => 'tipo de proyecto', 'description' => 'descripción', 'consent' => 'consentimiento', 'attachment' => 'archivo', 'external_url' => 'enlace externo'];
    }

    public function messages(): array
    {
        return ['attachment.uploaded' => 'No se pudo recibir el archivo. Revisa que no supere 50 MiB o utiliza un enlace externo.', 'attachment.max' => 'El archivo supera 50 MiB. Utiliza un enlace externo.'];
    }
}

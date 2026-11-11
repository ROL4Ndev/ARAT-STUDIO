<?php

namespace App\Http\Requests\PublicSite;

use App\Rules\ProjectAttachment;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends CalculateQuotationRequest
{
    public const SERVICES = ['Arquitectura', 'Diseño de interiores', 'Modelado BIM', 'Visualización 3D'];

    public const PROJECT_TYPES = ['Vivienda', 'Comercio', 'Oficina', 'Otro'];

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'submission_token' => ['required', 'uuid', function ($attribute, $value, $fail) {
                if (! in_array($value, $this->session()->get('project_submission_tokens', []), true)) {
                    $fail('El formulario ha vencido. Abre nuevamente Solicitar proyecto.');
                }
            }],
            'client_name' => ['required', 'string', 'max:150'],
            'client_email' => ['required', 'email', 'max:255'],
            'client_phone' => ['required', 'string', 'regex:/^[+()0-9\s-]{6,30}$/'],
            'city' => ['required', 'string', 'max:120'],
            'service' => ['required', Rule::in(self::SERVICES)],
            'project_type' => ['required', Rule::in(self::PROJECT_TYPES)],
            'description' => ['required', 'string', 'min:10', 'max:10000'],
            'consent' => ['required', 'accepted'],
            'attachment' => ['bail', 'nullable', 'file', 'max:51200', new ProjectAttachment],
            'external_url' => ['nullable', 'url:http,https', 'max:2048'],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'attachment.uploaded' => 'No se pudo recibir el archivo. Revisa que no supere 50 MB o utiliza un enlace externo.',
            'attachment.max' => 'El archivo supera 50 MB. Selecciona uno más pequeño o utiliza un enlace externo.',
        ]);
    }

    public function attributes(): array
    {
        return ['client_name' => 'nombre', 'client_email' => 'correo', 'client_phone' => 'teléfono', 'city' => 'ciudad', 'service' => 'servicio', 'project_type' => 'tipo de proyecto', 'description' => 'descripción', 'consent' => 'consentimiento', 'attachment' => 'archivo', 'external_url' => 'enlace externo'];
    }
}

<?php

namespace App\Http\Requests\Commercial;

use App\Models\Deliverable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DeliverableDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('deliverable')
            ? Gate::allows('view', $this->route('deliverable'))
            : Gate::allows('create', [Deliverable::class, $this->route('serviceRequest')]);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['title' => trim((string) $this->input('title')), 'description' => trim((string) $this->input('description'))]);
    }

    public function rules(): array
    {
        return ['title' => 'required|string|max:160', 'description' => 'required|string|max:5000',
            'internal_note' => 'nullable|string|max:2000', 'operation_key' => 'required|uuid',
            'quotation_code' => $this->route('deliverable') ? 'prohibited' : 'required|string|max:40',
            'lock_version' => $this->route('deliverable') ? 'required|integer|min:0' : 'prohibited',
            'attachment' => 'nullable|file|max:10240|mimes:pdf,png,jpg,jpeg|mimetypes:application/pdf,image/png,image/jpeg'];
    }

    public function attributes(): array
    {
        return ['title' => 'título', 'description' => 'descripción', 'attachment' => 'archivo', 'quotation_code' => 'cotización aceptada'];
    }

    protected function getRedirectUrl(): string
    {
        return $this->route('deliverable') ? route('commercial.deliverables.show', $this->route('deliverable'))
            : route('commercial.deliverables.create', $this->route('serviceRequest'));
    }

    public function messages(): array
    {
        return ['attachment.max' => 'El archivo debe pesar como máximo 10 MiB.',
            'attachment.mimes' => 'Adjunta un PDF, PNG o JPEG válido.', 'attachment.mimetypes' => 'El contenido no corresponde a un PDF, PNG o JPEG.'];
    }
}

<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class DeliverableResponseRequest extends FormRequest
{
    public function authorize(): bool { return $this->attributes->has('clientGrant') && ! $this->user(); }

    protected function prepareForValidation(): void
    {
        $this->merge(['comment' => trim((string) $this->input('comment')), 'action' => 'delivery-'.$this->route('deliverable')->code]);
    }

    public function rules(): array
    {
        return ['decision' => 'required|in:observado,conforme', 'lock_version' => 'required|integer|min:0',
            'action' => 'required|string|max:80',
            'comment' => 'required_if:decision,observado|nullable|string|max:5000',
            'confirmation' => 'exclude_unless:decision,conforme|required|accepted'];
    }

    protected function getRedirectUrl(): string
    {
        return route('client.dossier').'#entregables';
    }

    public function messages(): array
    {
        return ['comment.required_if' => 'Describe qué debe revisarse en el documento.',
            'confirmation.required' => 'Confirma que revisaste esta versión antes de dar conformidad.',
            'confirmation.accepted' => 'Confirma que revisaste esta versión antes de dar conformidad.'];
    }
}

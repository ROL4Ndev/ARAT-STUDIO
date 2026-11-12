<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->has('clientGrant');
    }

    public function rules(): array
    {
        return ['decision' => ['required', Rule::in(['aceptada', 'rechazada'])], 'lock_version' => ['required', 'integer', 'min:0'], 'reason' => ['required_if:decision,rechazada', 'nullable', 'string', 'max:5000']];
    }
}

<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class ClientActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->has('clientGrant');
    }

    public function rules(): array
    {
        return str_ends_with($this->route()->getName(), '.email') ? ['email' => ['required', 'email', 'max:255']] : ['lock_version' => ['required', 'integer', 'min:0'], 'reason' => ['required', 'string', 'min:5', 'max:5000']];
    }
}

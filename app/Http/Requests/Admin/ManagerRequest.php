<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ManagerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $manager = $this->route('manager');

        return $manager ? $this->user()?->can('update', $manager) === true : $this->user()?->can('create', User::class) === true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
        }
    }

    public function rules(): array
    {
        $manager = $this->route('manager');
        $rules = [
            'role' => ['prohibited'], 'is_admin' => ['prohibited'],
            'auth_version' => ['prohibited'], 'email_verified_at' => ['prohibited'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'lock_version' => [$manager ? 'required' : 'nullable', 'integer', 'min:0'],
        ];
        if ($this->routeIs('admin.team.password')) {
            return $rules + [
                'current_password' => ['required', 'current_password'],
                'password' => ['required', 'string', Password::min(12), 'confirmed', 'max:255'],
            ];
        }
        if ($this->routeIs('admin.team.status')) {
            return $rules + [
                'is_active' => ['required', 'boolean'],
                'expected_open_count' => ['required', 'integer', 'min:0'],
                'replacement_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            ];
        }

        return $rules + [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($manager?->id)],
            'password' => $manager ? ['prohibited'] : ['required', 'string', Password::min(12), 'confirmed', 'max:255'],
            'is_active' => ['prohibited'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nombre', 'email' => 'correo', 'password' => 'contraseña del gestor', 'current_password' => 'contraseña del administrador', 'reason' => 'motivo', 'replacement_id' => 'nuevo responsable', 'lock_version' => 'versión del registro'];
    }
}

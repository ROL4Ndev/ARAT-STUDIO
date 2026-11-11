<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator as ValidatorInstance;

class CreateInternalUser extends Command
{
    protected $signature = 'usuarios:crear-interno';

    protected $description = 'Crear una cuenta interna de administrador o gestor';

    public function handle(): int
    {
        $name = $this->askForName();
        $email = $this->askForEmail();
        $role = $this->askForRole();
        $password = $this->askForPassword();

        $user = new User;
        $user->name = $name;
        $user->email = $email;
        $user->role = $role;
        $user->password = $password;
        $user->save();

        $this->info("Se creó la cuenta interna de {$user->name} con rol {$role}.");

        return self::SUCCESS;
    }

    private function askForName(): string
    {
        do {
            $name = trim((string) $this->ask('Nombre completo'));
            $validator = Validator::make(
                ['name' => $name],
                ['name' => ['required', 'string', 'max:255']],
            );
            $this->showValidationErrors($validator);
        } while ($validator->fails());

        return $name;
    }

    private function askForEmail(): string
    {
        do {
            $email = mb_strtolower(trim((string) $this->ask('Correo electrónico')));
            $validator = Validator::make(
                ['email' => $email],
                ['email' => ['required', 'string', 'email', 'max:255', 'unique:users,email']],
            );
            $this->showValidationErrors($validator);
        } while ($validator->fails());

        return $email;
    }

    private function askForRole(): string
    {
        do {
            $role = mb_strtolower(trim((string) $this->ask('Rol interno (admin o gestor)')));
            $validator = Validator::make(
                ['role' => $role],
                ['role' => ['required', 'in:admin,gestor']],
            );
            $this->showValidationErrors($validator);
        } while ($validator->fails());

        return $role;
    }

    private function askForPassword(): string
    {
        do {
            $password = (string) $this->secret('Contraseña (mínimo 8 caracteres)');
            $confirmation = (string) $this->secret('Confirma la contraseña');
            $validator = Validator::make(
                [
                    'password' => $password,
                    'password_confirmation' => $confirmation,
                ],
                [
                    'password' => ['required', 'string', Password::min(8), 'confirmed'],
                ],
            );
            $this->showValidationErrors($validator);
        } while ($validator->fails());

        return $password;
    }

    private function showValidationErrors(ValidatorInstance $validator): void
    {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthenticationAndProfileTest extends TestCase
{
    use RefreshDatabase;

    private string $testPassword;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testPassword = Str::random(32);
    }

    public function test_guest_can_view_login_form_in_peruvian_spanish(): void
    {
        $this->get('/ingresar')
            ->assertOk()
            ->assertSee('<html lang="es-PE">', false)
            ->assertSee('Ingresar');
    }

    public function test_user_can_sign_in_with_valid_credentials(): void
    {
        $user = $this->createUser('gestor', 'gestor@example.test', $this->testPassword);

        $this->post('/ingresar', [
            'email' => $user->email,
            'password' => $this->testPassword,
        ])->assertRedirect(route('panel'));

        $this->assertAuthenticatedAs($user);
        $this->get('/panel')->assertRedirect(route('management.dashboard'));
    }

    public function test_invalid_credentials_show_a_spanish_error(): void
    {
        $user = $this->createUser('admin', 'admin@example.test', $this->testPassword);

        $this->from('/ingresar')->post('/ingresar', [
            'email' => $user->email,
            'password' => 'incorrecta',
        ])->assertRedirect('/ingresar')
            ->assertSessionHasErrors([
                'email' => 'Estas credenciales no coinciden con nuestros registros.',
            ]);

        $this->assertGuest();
    }

    public function test_login_attempts_are_limited_per_email_and_ip(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/ingresar', [
                'email' => 'desconocido@example.test',
                'password' => 'incorrecta',
            ]);
        }

        $this->from('/ingresar')->post('/ingresar', [
            'email' => 'desconocido@example.test',
            'password' => 'incorrecta',
        ])->assertRedirect('/ingresar')
            ->assertSessionHasErrors('email');

        $this->assertStringContainsString(
            'Demasiados intentos de inicio de sesión.',
            session('errors')->first('email'),
        );
    }

    public function test_authenticated_user_can_sign_out(): void
    {
        $user = $this->createUser('admin', 'admin@example.test', $this->testPassword);

        $this->actingAs($user)
            ->post('/salir')
            ->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_panel_routes_enforce_internal_roles(): void
    {
        $admin = $this->createUser('admin', 'admin@example.test', $this->testPassword);
        $gestor = $this->createUser('gestor', 'gestor@example.test', $this->testPassword);
        $userWithoutRole = $this->createUser(null, 'public@example.test', $this->testPassword);

        $this->get('/admin')->assertRedirect('/ingresar');

        $this->actingAs($admin)->get('/panel')->assertRedirect(route('admin.dashboard'));
        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->actingAs($admin)->get('/gestion')->assertOk();
        $this->actingAs($gestor)->get('/admin')->assertForbidden();
        $this->actingAs($gestor)->get('/gestion')->assertOk();
        $this->actingAs($userWithoutRole)->get('/panel')->assertForbidden();
        $this->actingAs($userWithoutRole)->get('/admin')->assertForbidden();
    }

    public function test_profile_update_ignores_role_and_user_id_submitted_by_client(): void
    {
        $gestor = $this->createUser('gestor', 'gestor@example.test', $this->testPassword);
        $otherUser = $this->createUser('admin', 'admin@example.test', $this->testPassword);

        $this->actingAs($gestor)->put('/perfil', [
            'name' => 'Nombre actualizado',
            'email' => 'actualizado@example.test',
            'role' => 'admin',
            'user_id' => $otherUser->id,
        ])->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('users', [
            'id' => $gestor->id,
            'name' => 'Nombre actualizado',
            'email' => 'actualizado@example.test',
            'role' => 'gestor',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $otherUser->id,
            'name' => $otherUser->name,
            'email' => $otherUser->email,
            'role' => 'admin',
        ]);
    }

    public function test_profile_email_must_be_unique(): void
    {
        $user = $this->createUser('gestor', 'gestor@example.test', $this->testPassword);
        $this->createUser('admin', 'admin@example.test', $this->testPassword);

        $this->actingAs($user)->from('/perfil')->put('/perfil', [
            'name' => 'Gestor',
            'email' => 'admin@example.test',
        ])->assertRedirect('/perfil')
            ->assertSessionHasErrors('email');
    }

    public function test_user_can_change_password_after_confirming_current_password(): void
    {
        $newPassword = Str::random(32);
        $user = $this->createUser('gestor', 'gestor@example.test', $this->testPassword);

        $this->actingAs($user)->put('/perfil/contrasena', [
            'current_password' => $this->testPassword,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status');

        $this->assertTrue(Hash::check($newPassword, $user->fresh()->password));
    }

    public function test_console_command_creates_an_internal_user_with_a_confirmed_password(): void
    {
        $this->artisan('usuarios:crear-interno')
            ->expectsQuestion('Nombre completo', 'Cuenta interna')
            ->expectsQuestion('Correo electrónico', 'cuenta@example.test')
            ->expectsQuestion('Rol interno (admin o gestor)', 'admin')
            ->expectsQuestion('Contraseña (mínimo 8 caracteres)', $this->testPassword)
            ->expectsQuestion('Confirma la contraseña', $this->testPassword)
            ->expectsOutputToContain('Se creó la cuenta interna de Cuenta interna con rol admin.')
            ->assertExitCode(0);

        $user = User::query()->where('email', 'cuenta@example.test')->firstOrFail();

        $this->assertSame('admin', $user->role);
        $this->assertTrue(Hash::check($this->testPassword, $user->password));
    }

    private function createUser(?string $role, string $email, string $password): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'password' => $password,
        ]);

        if ($role !== null) {
            $user->forceFill(['role' => $role])->save();
        }

        return $user;
    }
}

<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CommercialFixtures;
use Tests\TestCase;

class TeamManagementTest extends TestCase
{
    use CommercialFixtures, RefreshDatabase;

    private User $admin;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->admin = User::factory()->create(['role' => 'admin', 'password' => 'Admin-test-12345']);
        $this->manager = User::factory()->create(['role' => 'gestor']);
        $this->tariff();
    }

    private function data(): array
    {
        return ['name' => 'Gestora de prueba', 'email' => 'gestora@example.test', 'password' => 'Cuenta-test-12345', 'password_confirmation' => 'Cuenta-test-12345', 'reason' => 'Alta para atender solicitudes'];
    }

    public function test_only_admins_can_manage_managers_and_cannot_promote_or_modify_an_admin(): void
    {
        $this->get(route('admin.team.index'))->assertRedirect(route('login'));
        $this->actingAs($this->manager)->get(route('admin.team.index'))->assertForbidden();
        $this->post(route('admin.team.store'), $this->data())->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.team.show', $this->admin))->assertForbidden();
        $this->post(route('admin.team.store'), [...$this->data(), 'role' => 'admin'])->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'gestora@example.test']);
        $this->delete(route('admin.team.show', $this->manager))->assertStatus(405);
    }

    public function test_creation_edit_search_and_audit_never_expose_credentials(): void
    {
        $this->actingAs($this->admin)->post(route('admin.team.store'), $this->data())->assertSessionHasNoErrors()->assertRedirect();
        $manager = User::where('email', 'gestora@example.test')->firstOrFail();
        $this->assertSame('gestor', $manager->role);
        $this->assertTrue(Hash::check($this->data()['password'], $manager->password));
        $this->get(route('admin.team.index', ['q' => 'gestora@example.test']))->assertOk()->assertSee($manager->name)->assertDontSee($this->admin->email);
        $this->get(route('admin.team.show', $manager))->assertOk()->assertDontSee($manager->password)->assertDontSee($this->data()['password']);
        $this->put(route('admin.team.update', $manager), ['name' => 'Nuevo nombre', 'email' => 'nuevo@example.test', 'reason' => 'Actualizar datos de contacto', 'lock_version' => $manager->lock_version])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('gestor', $manager->fresh()->role);
        $audit = AuditEvent::where('resource_type', User::class)->get()->toJson();
        $this->assertStringNotContainsString($manager->password, $audit);
        $this->assertStringNotContainsString($this->data()['password'], $audit);
        $this->assertStringNotContainsString('password', $audit);
        $this->put(route('admin.team.update', $manager), ['name' => 'Vista antigua', 'email' => 'antiguo@example.test', 'reason' => 'Cambio desde una vista anterior', 'lock_version' => $manager->lock_version])->assertStatus(409);
    }

    public function test_password_confirmation_and_unique_email_are_required(): void
    {
        $this->actingAs($this->admin)->post(route('admin.team.store'), [...$this->data(), 'password_confirmation' => 'Otra-clave-12345'])->assertSessionHasErrors('password');
        $this->post(route('admin.team.store'), [...$this->data(), 'email' => $this->manager->email])->assertSessionHasErrors('email');
        $this->assertSame(2, User::count());
    }

    public function test_deactivation_requires_reassignment_preserves_closed_history_and_revokes_access(): void
    {
        $replacement = User::factory()->create(['role' => 'gestor']);
        $open = $this->registerRequest();
        $closed = $this->registerRequest();
        foreach ([$open, $closed] as $request) {
            $this->action($request, $this->manager, 'claim');
        }
        $this->action($closed, $this->manager, 'cancel');
        $data = ['lock_version' => 0, 'is_active' => 0, 'expected_open_count' => 1, 'reason' => 'Cambio de responsable del equipo'];
        $url = route('admin.team.status', $this->manager);
        $this->actingAs($this->admin)->post($url, $data)->assertSessionHasErrors('replacement_id');
        $this->assertTrue($this->manager->fresh()->is_active);
        $this->post($url, $data + ['replacement_id' => $replacement->id])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertFalse($this->manager->fresh()->is_active);
        $this->assertSame($replacement->id, $open->fresh()->assigned_to);
        $this->assertSame($this->manager->id, $closed->fresh()->assigned_to);
        $this->assertTrue($open->histories()->where('action', 'assign')->exists());
        $this->actingAs($this->manager->fresh())->get('/gestion')->assertForbidden();
    }

    public function test_reset_requires_admin_password_and_invalidates_old_sessions(): void
    {
        $data = ['lock_version' => 0, 'current_password' => 'incorrecta', 'password' => 'Nueva-clave-12345', 'password_confirmation' => 'Nueva-clave-12345', 'reason' => 'Restablecimiento solicitado por el gestor'];
        $url = route('admin.team.password', $this->manager);
        $this->actingAs($this->admin)->post($url, $data)->assertSessionHasErrors('current_password');
        $this->post($url, [...$data, 'current_password' => 'Admin-test-12345'])->assertSessionHasNoErrors()->assertRedirect();
        $this->manager->refresh();
        $this->assertTrue(Hash::check('Nueva-clave-12345', $this->manager->password));
        $this->actingAs($this->manager)->withSession(['auth_version' => 0])->get('/gestion')->assertForbidden();
        $this->post(route('login.store'), ['email' => $this->manager->email, 'password' => 'Nueva-clave-12345'])->assertRedirect();
        $this->get('/gestion')->assertOk();
        $this->assertStringNotContainsString('Nueva-clave-12345', AuditEvent::all()->toJson());
    }

    public function test_new_assignments_and_status_changes_are_detected_before_deactivation(): void
    {
        $request = $this->registerRequest();
        $this->action($request, $this->manager, 'claim');
        $this->actingAs($this->admin)->post(route('admin.team.status', $this->manager), [
            'lock_version' => 0, 'is_active' => 0, 'expected_open_count' => 0,
            'replacement_id' => $this->admin->id, 'reason' => 'Vista anterior a la nueva asignación',
        ])->assertStatus(409);
        $this->assertTrue($this->manager->fresh()->is_active);
        $this->assertSame($this->manager->id, $request->fresh()->assigned_to);
    }

    public function test_audit_failure_rolls_back_reassignment_and_deactivation_together(): void
    {
        $request = $this->registerRequest();
        $this->action($request, $this->manager, 'claim');
        $version = $request->lock_version;
        $this->mock(AuditService::class)->shouldReceive('record')->andThrow(new \RuntimeException('Fallo simulado de auditoría'));
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($this->admin)->post(route('admin.team.status', $this->manager), [
                'lock_version' => 0, 'is_active' => false, 'expected_open_count' => 1,
                'replacement_id' => $this->admin->id, 'reason' => 'Reasignación y desactivación atómicas',
            ]);
            $this->fail('Se esperaba el fallo simulado.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Fallo simulado de auditoría', $error->getMessage());
        }
        $this->assertTrue($this->manager->fresh()->is_active);
        $this->assertSame(0, $this->manager->fresh()->auth_version);
        $this->assertSame($version, $request->fresh()->lock_version);
        $this->assertSame($this->manager->id, $request->fresh()->assigned_to);
        $this->assertFalse($request->histories()->where('action', 'assign')->exists());
    }
}

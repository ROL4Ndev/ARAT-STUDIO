<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ClientRevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CommercialFixtures;
use Tests\TestCase;

class CommercialWorkflowTest extends TestCase
{
    use CommercialFixtures,RefreshDatabase;

    private $r;

    private $manager;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('commercial');
        Mail::fake();
        $this->tariff();
        $this->r = $this->registerRequest('80', UploadedFile::fake()->image('plano.png'));
        $this->manager = User::factory()->create(['role' => 'gestor']);
    }

    public function test_unassigned_queue_is_minimal_and_foreign_manager_cannot_view_or_download(): void
    {
        $url = route('commercial.requests.show', $this->r);
        $this->get($url)->assertRedirect('/ingresar');
        $this->actingAs($this->manager)->get('/gestion')->assertOk()->assertSee($this->r->code)->assertDontSee($this->r->client_email)->assertDontSee($this->r->client_phone);
        $this->get($url)->assertForbidden();
        $this->get(route('commercial.requests.attachment', $this->r))->assertForbidden();
        $this->action($this->r, $this->manager, 'claim');
        $this->get($url)->assertOk();
        $this->get(route('commercial.requests.attachment', $this->r))->assertDownload();
        $other = User::factory()->create(['role' => 'gestor']);
        $this->actingAs($other)->get($url)->assertForbidden();
        $this->get('/admin/categorias')->assertForbidden();
    }

    public function test_stale_claim_and_invalid_transitions_conflict(): void
    {
        $url = route('commercial.requests.transition', $this->r);
        $this->action($this->r, $this->manager, 'claim');
        $this->actingAs($this->manager)->patch($url, ['action' => 'claim', 'reason' => 'Intento atrasado', 'lock_version' => 0])->assertStatus(409);
        $this->patch($url, ['action' => 'approve', 'reason' => 'Saltar evaluación', 'lock_version' => $this->r->lock_version, 'assessment' => 'Evaluación técnica que aún no corresponde.'])->assertStatus(409);
        $this->action($this->r, $this->manager, 'start');
        $this->patch($url, ['action' => 'reject', 'lock_version' => $this->r->lock_version])->assertSessionHasErrors('reason');
    }

    public function test_observation_correction_preserves_revisions_files_and_needs_responsible_resume(): void
    {
        $this->action($this->r, $this->manager, 'claim');
        $this->action($this->r, $this->manager, 'start');
        $this->action($this->r, $this->manager, 'observe', ['public_observation' => 'Corrige la descripción y adjunta otra referencia.']);
        [$grant] = $this->clientGrant($this->r);
        app(ClientRevisionService::class)->correct($this->r, $grant, ['lock_version' => $this->r->lock_version, 'client_name' => 'Nombre corregido', 'client_phone' => '999000111', 'city' => 'Lima', 'project_type' => 'Vivienda', 'description' => 'Descripción corregida válida para evaluación técnica.', 'area_m2' => '80', 'lod_selected' => 'LOD_300', 'reason' => 'Corrección solicitada por el equipo.'], UploadedFile::fake()->image('otro.png'));
        $this->r->refresh();
        $this->assertCount(2, $this->r->revisions);
        $this->assertSame($grant->id, $this->r->currentRevision->client_grant_id);
        $this->assertSame($this->r->attachment_path, $this->r->currentRevision->after['attachment_path']);
        $this->assertCount(2, $this->r->files);
        foreach ($this->r->files as $file) {
            Storage::disk('commercial')->assertExists($file->path);
        }
        $this->assertSame('evaluacion', $this->r->fresh()->status);
        $this->action($this->r, $this->manager, 'approve', ['assessment' => 'Evaluación técnica posterior a la corrección válida.']);
        $this->assertSame('aprobado', $this->r->status);
    }

    public function test_reassignment_revokes_previous_manager_access_and_disabled_account_loses_session(): void
    {
        $this->action($this->r, $this->manager, 'claim');
        $admin = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'gestor']);
        $this->action($this->r, $admin, 'assign', ['assigned_to' => $other->id]);
        $this->actingAs($this->manager)->get(route('commercial.requests.show', $this->r))->assertForbidden();
        $this->actingAs($other)->get(route('commercial.requests.show', $this->r))->assertOk();
        $other->forceFill(['is_active' => false])->save();
        $this->get('/gestion')->assertForbidden();
        $this->assertGuest();
    }
}

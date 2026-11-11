<?php

namespace Tests\Feature;

use App\Models\ServiceRequest;
use App\Models\TariffParam;
use App\Models\User;
use App\Services\ServiceRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommercialWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private ServiceRequest $record;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('commercial');
        TariffParam::create(['lod_level' => 'LOD_300', 'base_price_m2' => '100', 'lod_factor' => '1.35', 'max_area_m2' => '500']);
        [$this->record] = app(ServiceRequestService::class)->register(['submission_token' => (string) Str::uuid(), 'client_name' => 'Ana', 'client_email' => 'ana@example.test', 'client_phone' => '999888777', 'city' => 'Lima', 'service' => 'Arquitectura', 'project_type' => 'Vivienda', 'description' => 'Vivienda familiar', 'area_m2' => '80', 'lod_selected' => 'LOD_300'], UploadedFile::fake()->image('plano.png'));
    }

    public function test_access_and_private_download(): void
    {
        $url = route('commercial.requests.attachment', $this->record);
        $this->get('/gestion')->assertRedirect('/ingresar');
        $this->get($url)->assertRedirect('/ingresar');
        $this->actingAs(User::factory()->create(['role' => null]))->get($url)->assertForbidden();
        $this->get('/gestion')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'gestor']))->get('/gestion')->assertOk()->assertSee($this->record->code);
        $this->get(route('commercial.requests.show', $this->record))->assertOk();
        $this->get($url)->assertOk()->assertDownload();
        $this->get($url.'?preview=1&path=../../.env')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/admin/categorias')->assertForbidden();
    }

    public function test_transitions_and_rejection_require_reason(): void
    {
        $user = User::factory()->create(['role' => 'gestor']);
        $url = route('commercial.requests.transition', $this->record);
        $this->actingAs($user)->patch($url, ['expected_status' => 'pendiente', 'status' => 'aprobado'])->assertSessionHasErrors('status');
        $this->patch($url, ['expected_status' => 'pendiente', 'status' => 'evaluacion'])->assertSessionHasNoErrors();
        $this->patch($url, ['expected_status' => 'pendiente', 'status' => 'evaluacion'])->assertSessionHasErrors('status');
        $this->patch($url, ['expected_status' => 'evaluacion', 'status' => 'rechazado'])->assertSessionHasErrors('observation');
        $this->assertSame('evaluacion', $this->record->fresh()->status);
        $this->patch($url, ['expected_status' => 'evaluacion', 'status' => 'rechazado', 'observation' => 'Fuera de alcance.'])->assertRedirect();
        $this->assertSame('Fuera de alcance.', $this->record->fresh()->rejection_reason);
        $this->assertDatabaseHas('service_request_histories', ['user_id' => $user->id, 'previous_status' => 'evaluacion', 'new_status' => 'rechazado']);
        $this->patch($url, ['expected_status' => 'evaluacion', 'status' => 'aprobado'])->assertSessionHasErrors('status');
        $this->assertCount(3, $this->record->histories);
    }

    public function test_admin_can_approve_after_evaluation_and_final_status_cannot_go_back(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $url = route('commercial.requests.transition', $this->record);
        $this->patch($url, ['expected_status' => 'pendiente', 'status' => 'evaluacion'])->assertSessionHasNoErrors();
        $this->patch($url, ['expected_status' => 'evaluacion', 'status' => 'aprobado', 'observation' => 'Alcance viable.'])->assertSessionHasNoErrors();
        $this->assertSame('aprobado', $this->record->fresh()->status);
        $this->patch($url, ['expected_status' => 'evaluacion', 'status' => 'rechazado', 'observation' => 'Actualización atrasada.'])->assertSessionHasErrors('status');
        $this->assertSame('aprobado', $this->record->fresh()->status);
    }

    public function test_search_status_filter_and_pagination_keep_query(): void
    {
        for ($i = 0; $i < 16; $i++) {
            $copy = $this->record->replicate();
            $copy->code = 'SOL-'.Str::ulid();
            $copy->submission_token = (string) Str::uuid();
            $copy->save();
        }
        $this->actingAs(User::factory()->create(['role' => 'gestor']));
        $response = $this->get('/gestion?search=ana&status=pendiente')->assertOk();
        $this->assertCount(15, $response->viewData('requests'));
        $this->assertSame(17, $response->viewData('requests')->total());
        $this->assertStringContainsString('status=pendiente', $response->viewData('requests')->nextPageUrl());
        $this->get('/gestion?status=aprobado')->assertOk()->assertSee('No hay solicitudes');
        $this->get('/gestion?search='.$this->record->code)->assertOk()->assertSee($this->record->code);
        $this->get('/gestion/solicitudes/'.$this->record->id)->assertNotFound();
    }
}

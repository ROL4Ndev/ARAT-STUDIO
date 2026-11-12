<?php

namespace Tests\Feature;

use App\Models\NotificationRecord;
use App\Models\OfferedService;
use App\Models\ServiceRequest;
use App\Models\TariffGroup;
use App\Models\WorkIntent;
use App\Services\QuotationCalculatorService;
use App\Services\WorkProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CommercialFixtures;
use Tests\TestCase;

class PublicServiceRequestTest extends TestCase
{
    use CommercialFixtures,RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('commercial');
        Mail::fake();
        $this->tariff();
    }

    private function data(string $area = '80'): array
    {
        $d = $this->requestData($area);
        $this->withSession(['project_submission_tokens' => [$d['submission_token']]]);

        return $d;
    }

    public function test_registration_recalculates_and_identical_retry_is_authorized_but_other_content_conflicts(): void
    {
        $this->get('/solicitar-proyecto')->assertOk();
        $d = $this->data();
        $this->post('/solicitar-proyecto', $d + ['estimated_cost' => '1', 'status' => 'aprobado', 'lod_factor' => '0'])->assertSessionHasNoErrors()->assertRedirect(route('service-requests.confirmation'));
        $r = ServiceRequest::firstOrFail();
        $this->assertSame('10800.00', $r->estimated_cost);
        $this->assertSame('pendiente', $r->status);
        $this->post('/solicitar-proyecto', $d)->assertRedirect();
        $this->assertDatabaseCount('service_requests', 1);
        $this->post('/solicitar-proyecto', [...$d, 'description' => 'Contenido cambiado para otro propósito.'])->assertStatus(409);
        $this->flushSession();
        $this->post('/solicitar-proyecto', $d)->assertSessionHasErrors('submission_token');
        $this->get('/solicitar-proyecto/confirmacion')->assertNotFound();
    }

    public function test_tariff_change_requires_conflict_and_confirmation_without_altering_history(): void
    {
        $d = $this->data();
        $old = $this->registerRequest();
        $this->tariff('110');
        $this->post('/solicitar-proyecto', $d)->assertStatus(409)->assertSee('estimación cambió');
        $this->assertDatabaseCount('service_requests', 1);
        $d['estimate_token'] = app(QuotationCalculatorService::class)->calculate('80', 'LOD_300')['estimate_token'];
        $this->post('/solicitar-proyecto', $d)->assertRedirect();
        $this->assertSame('11880.00', ServiceRequest::latest('id')->first()->estimated_cost);
        $this->assertSame('10800.00', $old->fresh()->estimated_cost);
    }

    public function test_manual_out_of_scale_and_missing_tariff_can_be_registered(): void
    {
        $d = $this->data('501');
        $this->post('/solicitar-proyecto', $d)->assertRedirect();
        $this->assertNull(ServiceRequest::first()->estimated_cost);
        $s = OfferedService::where('code', 'VISUALIZACION')->first();
        $s->update(['estimation_mode' => 'manual']);
        $d = $this->requestData('80', $s);
        $this->withSession(['project_submission_tokens' => [$d['submission_token']]]);
        $this->post('/solicitar-proyecto', $d)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNull(ServiceRequest::latest('id')->first()->area_m2);
    }

    public function test_inactive_service_and_invalid_contact_precision_consent_are_rejected(): void
    {
        $d = $this->data();
        $this->post('/solicitar-proyecto', [...$d, 'area_m2' => '1.123', 'client_email' => 'no-correo', 'consent' => '0', 'external_url' => 'javascript:alert(1)'])->assertSessionHasErrors(['area_m2', 'client_email', 'consent', 'external_url']);
        OfferedService::whereKey($d['service_id'])->update(['is_active' => false]);
        $this->post('/solicitar-proyecto', $d)->assertStatus(422);
        $this->assertDatabaseCount('service_requests', 0);
    }

    public function test_attachment_content_and_size_validation_and_private_storage(): void
    {
        $d = $this->data();
        $this->post('/solicitar-proyecto', $d + ['attachment' => UploadedFile::fake()->createWithContent('plano.pdf', '<?php echo 1;')])->assertSessionHasErrors('attachment');
        $this->post('/solicitar-proyecto', $d + ['attachment' => UploadedFile::fake()->create('plano.zip', 51201)])->assertSessionHasErrors('attachment');
        $this->post('/solicitar-proyecto', $d + ['attachment' => new UploadedFile('', 'plano.pdf', 'application/pdf', UPLOAD_ERR_INI_SIZE, true)])->assertSessionHasErrors('attachment');
        $this->post('/solicitar-proyecto', $d + ['attachment' => UploadedFile::fake()->image('plano.png')])->assertRedirect();
        $r = ServiceRequest::firstOrFail();
        Storage::disk('commercial')->assertExists($r->attachment_path);
        $this->assertCount(1, $r->files);
    }

    public function test_pdf_dwg_and_zip_content_is_accepted(): void
    {
        foreach (['plano.pdf' => "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF", 'plano.dwg' => 'AC1032'.str_repeat("\0", 256), 'planos.zip' => base64_decode('UEsFBgAAAAAAAAAAAAAAAAAAAAAAAA==')] as $name => $content) {
            $d = $this->data();
            $this->post('/solicitar-proyecto', $d + ['attachment' => UploadedFile::fake()->createWithContent($name, $content)])->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->assertDatabaseCount('service_requests', 3);
    }

    public function test_storage_failure_does_not_confirm_or_create_record(): void
    {
        $d = $this->data();
        Storage::shouldReceive('disk')->with('commercial')->andReturnSelf();
        Storage::shouldReceive('putFileAs')->andThrow(new \RuntimeException('disk full'));
        Storage::shouldReceive('delete')->andReturn(true);
        $this->post('/solicitar-proyecto', $d + ['attachment' => UploadedFile::fake()->image('plano.png')])->assertSessionHasErrors('attachment');
        $this->assertDatabaseCount('service_requests', 0);
    }

    public function test_database_failure_cleans_only_the_new_upload(): void
    {
        $d = $this->data();
        ServiceRequest::creating(fn () => throw new \RuntimeException('database unavailable'));
        try {
            $this->post('/solicitar-proyecto', $d + ['attachment' => UploadedFile::fake()->image('plano.png')])->assertSessionHasErrors('attachment');
            $this->assertDatabaseCount('service_requests', 0);
            $this->assertSame([], Storage::disk('commercial')->allFiles());
        } finally {
            ServiceRequest::flushEventListeners();
        }
    }

    public function test_mail_failure_is_durable_and_does_not_duplicate_request(): void
    {
        $d = $this->data();
        $this->post('/solicitar-proyecto', $d)->assertRedirect();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp failure'));
        app(WorkProcessor::class)->run(WorkIntent::where('kind', 'notification')->first()->id);
        $this->assertSame('failed', NotificationRecord::first()->status);
        $this->post('/solicitar-proyecto', $d)->assertRedirect();
        $this->assertDatabaseCount('service_requests', 1);
    }

    public function test_rate_limit_and_php_post_limit_have_controlled_responses(): void
    {
        $d = $this->data();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/solicitar-proyecto', $d)->assertRedirect();
        }
        $this->post('/solicitar-proyecto', $d)->assertStatus(429);
        $this->assertDatabaseCount('service_requests', 1);
        $this->withServerVariables(['CONTENT_LENGTH' => PHP_INT_MAX])->post('/solicitar-proyecto')->assertStatus(413)->assertSee('enlace externo');
    }

    public function test_estimation_requests_do_not_consume_submission_or_access_limits(): void
    {
        $d = $this->data();
        for ($i = 0; $i < 8; $i++) {
            $this->postJson('/api/calcular-presupuesto', ['service_id' => $d['service_id'], 'area_m2' => '80', 'lod_selected' => 'LOD_300'])->assertOk();
        }
        $this->post('/solicitar-proyecto', $d)->assertRedirect();
        $this->assertDatabaseCount('service_requests', 1);
        $r = ServiceRequest::first();
        $this->post('/acceder-expediente', ['code' => $r->code, 'email' => $r->client_email])->assertRedirect();
    }

    public function test_missing_tariff_allows_registration_with_null_estimate_and_reason(): void
    {
        TariffGroup::where('lod_level', 'LOD_300')->update(['active_tariff_id' => null]);
        $d = $this->data();
        $this->post('/solicitar-proyecto', $d)->assertRedirect();
        $r = ServiceRequest::firstOrFail();
        $this->assertNull($r->estimated_cost);
        $this->assertStringContainsString('No hay tarifa', $r->estimation_reason);
    }
}

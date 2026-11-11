<?php

namespace Tests\Feature;

use App\Mail\ServiceRequestReceived;
use App\Models\ServiceRequest;
use App\Models\TariffParam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicServiceRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('commercial');
        TariffParam::create(['lod_level' => 'LOD_300', 'base_price_m2' => '100', 'lod_factor' => '1.35', 'max_area_m2' => '500']);
    }

    private function data(): array
    {
        $token = (string) Str::uuid();
        $this->withSession(['project_submission_tokens' => [$token]]);

        return ['submission_token' => $token, 'client_name' => 'Ana Pérez', 'client_email' => 'ana@example.test', 'client_phone' => '999888777', 'city' => 'Lima', 'service' => 'Modelado BIM', 'project_type' => 'Vivienda', 'description' => 'Proyecto de vivienda familiar.', 'area_m2' => '80', 'lod_selected' => 'LOD_300', 'consent' => '1'];
    }

    public function test_public_registration_recalculates_and_preserves_snapshot_and_is_idempotent(): void
    {
        $this->get('/solicitar-proyecto')->assertOk();
        $data = [...$this->data(), 'estimated_cost' => '1', 'base_price_m2' => '1', 'status' => 'aprobado'];
        $this->post('/solicitar-proyecto', $data)->assertRedirect(route('service-requests.confirmation'));
        $record = ServiceRequest::firstOrFail();
        $this->assertSame('10800.00', $record->estimated_cost);
        $this->assertSame('pendiente', $record->status);
        $this->assertCount(1, $record->histories);
        TariffParam::query()->update(['base_price_m2' => '500']);
        $this->post('/solicitar-proyecto', $data)->assertRedirect();
        $this->assertDatabaseCount('service_requests', 1);
        $this->assertSame('100.00', $record->fresh()->base_price_m2);
        Mail::assertSent(ServiceRequestReceived::class, 1);
        $this->get('/solicitar-proyecto/confirmacion')->assertOk()->assertSee($record->code);
        $this->flushSession();
        $this->get('/solicitar-proyecto/confirmacion')->assertNotFound();
    }

    public function test_invalid_data_and_personalized_request(): void
    {
        $data = $this->data();
        $this->post('/solicitar-proyecto', [...$data, 'area_m2' => '0', 'consent' => '0', 'external_url' => 'javascript:alert(1)'])->assertSessionHasErrors(['area_m2', 'consent', 'external_url']);
        $this->post('/solicitar-proyecto', [...$data, 'area_m2' => '1.123'])->assertSessionHasErrors('area_m2');
        $this->post('/solicitar-proyecto', [...$data, 'area_m2' => '501'])->assertRedirect();
        $this->assertNull(ServiceRequest::first()->estimated_cost);
        $this->assertTrue(ServiceRequest::first()->custom_evaluation);
    }

    public function test_attachment_content_and_size_are_validated(): void
    {
        $data = $this->data();
        $this->post('/solicitar-proyecto', [...$data, 'attachment' => UploadedFile::fake()->createWithContent('plano.pdf', '<?php echo 1;')])->assertSessionHasErrors('attachment');
        $this->post('/solicitar-proyecto', [...$data, 'attachment' => UploadedFile::fake()->create('plano.zip', 51201)])->assertSessionHasErrors(['attachment' => 'El archivo supera 50 MB. Selecciona uno más pequeño o utiliza un enlace externo.']);
        $this->post('/solicitar-proyecto', [...$data, 'attachment' => new UploadedFile('', 'plano.pdf', 'application/pdf', UPLOAD_ERR_INI_SIZE, true)])->assertSessionHasErrors(['attachment' => 'No se pudo recibir el archivo. Revisa que no supere 50 MB o utiliza un enlace externo.']);
        $this->post('/solicitar-proyecto', [...$data, 'attachment' => UploadedFile::fake()->image('plano.png')])->assertRedirect();
        Storage::disk('commercial')->assertExists(ServiceRequest::firstOrFail()->attachment_path);
    }

    public function test_storage_failure_does_not_create_record(): void
    {
        Storage::shouldReceive('disk')->with('commercial')->andReturnSelf();
        Storage::shouldReceive('putFileAs')->andThrow(new \RuntimeException('disk full'));
        Storage::shouldReceive('delete')->andReturn(true);
        $this->post('/solicitar-proyecto', [...$this->data(), 'attachment' => UploadedFile::fake()->image('plano.png')])->assertSessionHasErrors('attachment');
        $this->assertDatabaseCount('service_requests', 0);
    }

    public function test_mail_failure_does_not_lose_or_duplicate_request(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('mail unavailable'));
        $data = $this->data();
        $this->post('/solicitar-proyecto', $data)->assertRedirect(route('service-requests.confirmation'));
        $this->post('/solicitar-proyecto', $data)->assertRedirect(route('service-requests.confirmation'));
        $this->assertDatabaseCount('service_requests', 1);
    }

    public function test_php_post_limit_has_controlled_error(): void
    {
        $this->withServerVariables(['CONTENT_LENGTH' => PHP_INT_MAX])->post('/solicitar-proyecto')->assertStatus(413)->assertSee('enlace externo');
    }

    public function test_pdf_dwg_and_zip_are_accepted_by_content_and_extension(): void
    {
        $files = [
            'plano.pdf' => "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF",
            'plano.dwg' => 'AC1032'.str_repeat("\0", 256),
            'planos.zip' => base64_decode('UEsFBgAAAAAAAAAAAAAAAAAAAAAAAA=='),
        ];
        foreach ($files as $name => $content) {
            $this->post('/solicitar-proyecto', [...$this->data(), 'attachment' => UploadedFile::fake()->createWithContent($name, $content)])->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->assertDatabaseCount('service_requests', 3);
    }

    public function test_public_submissions_are_rate_limited_in_spanish(): void
    {
        $data = $this->data();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/solicitar-proyecto', $data)->assertRedirect();
        }
        $this->post('/solicitar-proyecto', $data)->assertStatus(429)->assertSee('demasiados intentos');
        $this->assertDatabaseCount('service_requests', 1);
    }

    public function test_database_failure_cleans_the_new_upload(): void
    {
        ServiceRequest::creating(function () {
            throw new \RuntimeException('database unavailable');
        });
        try {
            $this->post('/solicitar-proyecto', [...$this->data(), 'attachment' => UploadedFile::fake()->image('plano.png')])->assertSessionHasErrors('attachment');
            $this->assertDatabaseCount('service_requests', 0);
            $this->assertSame([], Storage::disk('commercial')->allFiles());
        } finally {
            ServiceRequest::flushEventListeners();
        }
    }

    public function test_form_token_is_bound_to_session_and_public_records_have_no_detail_route(): void
    {
        $data = $this->data();
        $this->flushSession();
        $this->post('/solicitar-proyecto', $data)->assertSessionHasErrors('submission_token');
        $this->assertDatabaseCount('service_requests', 0);
        $this->get('/solicitar-proyecto/1')->assertNotFound();
    }
}

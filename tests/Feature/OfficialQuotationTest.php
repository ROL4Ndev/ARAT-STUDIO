<?php

namespace Tests\Feature;

use App\Models\Quotation;
use App\Models\ServiceRequest;
use App\Models\TariffParam;
use App\Models\User;
use App\Services\QuotationPdfService;
use App\Services\ServiceRequestService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfficialQuotationTest extends TestCase
{
    use RefreshDatabase;

    private ServiceRequest $record;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('commercial');
        TariffParam::create(['lod_level' => 'LOD_300', 'base_price_m2' => '100', 'lod_factor' => '1.35', 'max_area_m2' => '500']);
        [$this->record] = app(ServiceRequestService::class)->register(['submission_token' => (string) Str::uuid(), 'client_name' => 'Ana Pérez', 'client_email' => 'ana@example.test', 'client_phone' => '999888777', 'city' => 'Lima', 'service' => 'Arquitectura', 'project_type' => 'Vivienda', 'description' => 'Vivienda familiar', 'area_m2' => '80', 'lod_selected' => 'LOD_300'], null);
        $this->actingAs(User::factory()->create(['role' => 'gestor']));
    }

    private function data(): array
    {
        return ['scope' => 'Modelado y entrega de planos del proyecto.', 'official_amount' => '12000.50'];
    }

    public function test_only_approved_requests_can_be_quoted_and_input_is_validated(): void
    {
        $url = route('commercial.quotations.store', $this->record);
        $this->post($url, $this->data())->assertSessionHasErrors('scope');
        $this->record->update(['status' => 'aprobado']);
        $this->post($url, [...$this->data(), 'official_amount' => '0'])->assertSessionHasErrors('official_amount');
        $this->assertDatabaseCount('quotations', 0);
    }

    public function test_real_pdf_snapshots_expiry_private_download_and_idempotency(): void
    {
        $this->travelTo(Carbon::parse('2026-11-11 10:00:00', 'America/Lima'));
        $this->record->update(['status' => 'aprobado']);
        $url = route('commercial.quotations.store', $this->record);
        $this->post($url, $this->data())->assertSessionHasNoErrors()->assertRedirect();
        $quotation = Quotation::firstOrFail();
        $this->assertSame('2026-11-26', $quotation->expires_at->format('Y-m-d'));
        $this->assertSame('12000.50', $quotation->official_amount);
        $this->assertStringStartsWith('%PDF-', Storage::disk('commercial')->get($quotation->pdf_path));
        $this->post($url, $this->data())->assertRedirect(route('commercial.quotations.show', $quotation));
        $this->assertDatabaseCount('quotations', 1);
        $this->assertDatabaseCount('service_request_histories', 2);
        $this->record->update(['client_name' => 'Otro nombre']);
        $this->assertSame('Ana Pérez', $quotation->fresh()->client_snapshot['name']);
        $this->get(route('commercial.quotations.show', $quotation))->assertOk()->assertSee('Vigente');
        $this->get(route('commercial.quotations.download', $quotation))->assertOk()->assertDownload($quotation->code.'.pdf');
        $this->travelTo(now()->addDays(16));
        $this->get(route('commercial.quotations.show', $quotation))->assertSee('Vencida');
        $this->actingAs(User::factory()->create(['role' => null]))->get(route('commercial.quotations.download', $quotation))->assertForbidden();
        auth()->logout();
        $this->get(route('commercial.quotations.download', $quotation))->assertRedirect('/ingresar');
    }

    public function test_pdf_failure_rolls_back_and_allows_retry(): void
    {
        $this->record->update(['status' => 'aprobado']);
        $this->mock(QuotationPdfService::class)->shouldReceive('render')->once()->andThrow(new \RuntimeException('PDF failure'));
        $url = route('commercial.quotations.store', $this->record);
        $this->post($url, $this->data())->assertSessionHasErrors('scope');
        $this->assertDatabaseCount('quotations', 0);
        $this->assertCount(0, Storage::disk('commercial')->allFiles());
        $this->app->forgetInstance(QuotationPdfService::class);
        $this->post($url, $this->data())->assertRedirect();
        $this->assertDatabaseCount('quotations', 1);
    }

    public function test_pdf_storage_failure_rolls_back(): void
    {
        $this->record->update(['status' => 'aprobado']);
        Storage::shouldReceive('disk')->with('commercial')->andReturnSelf();
        Storage::shouldReceive('put')->andReturn(false);
        Storage::shouldReceive('delete')->andReturn(true);
        $this->post(route('commercial.quotations.store', $this->record), $this->data())->assertSessionHasErrors('scope');
        $this->assertDatabaseCount('quotations', 0);
        $this->assertDatabaseCount('service_request_histories', 1);
    }

    public function test_database_constraint_prevents_a_second_quotation_for_same_request(): void
    {
        $this->record->update(['status' => 'aprobado']);
        $this->post(route('commercial.quotations.store', $this->record), $this->data())->assertSessionHasNoErrors();
        $copy = Quotation::firstOrFail()->replicate();
        $copy->code = 'COT-'.Str::ulid();
        $this->expectException(UniqueConstraintViolationException::class);
        $copy->save();
    }
}

<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Quotation;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\WorkIntent;
use App\Models\TariffGroup;
use App\Services\QuotationCalculatorService;
use Database\Seeders\TariffParamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoPreparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_installation_initializes_current_tariff_groups_once(): void
    {
        $this->seed(TariffParamSeeder::class);
        $before = DB::table('tariff_params')->orderBy('id')->get()->toJson();
        foreach (['LOD_200' => '10000.00', 'LOD_300' => '13500.00', 'LOD_400' => '17500.00'] as $lod => $amount) {
            $this->assertNotNull(TariffGroup::where('lod_level', $lod)->firstOrFail()->active_tariff_id);
            $this->assertSame($amount, app(QuotationCalculatorService::class)->calculate('100', $lod)['estimated_cost_decimal']);
        }
        $this->seed(TariffParamSeeder::class);
        $this->assertSame($before, DB::table('tariff_params')->orderBy('id')->get()->toJson());
    }

    public function test_demo_uses_commercial_rules_valid_pdfs_simulated_mail_and_repeats_without_changes(): void
    {
        Storage::fake('commercial');
        $this->seed(TariffParamSeeder::class);
        $actor = User::factory()->create(['role' => 'admin']);
        $account = hash('sha256', serialize($actor->fresh()->getRawOriginal()));
        $arguments = ['--batch' => 'arat-2026-v1', '--actor' => $actor->id, '--date' => '2026-11-24', '--apply' => true];
        $this->artisan('demo:prepare', $arguments)->assertSuccessful();
        $requests = ServiceRequest::where('demo_batch', 'arat-2026-v1')->get()->keyBy('demo_scenario');
        $this->assertCount(15, $requests);
        $this->assertSame('pendiente', $requests['pendiente']->status);
        $this->assertNull($requests['pendiente']->assigned_to);
        $this->assertSame('evaluacion', $requests['evaluacion']->status);
        $this->assertSame('observada', $requests['observada']->status);
        $this->assertCount(2, $requests['corregida']->revisions);
        $this->assertSame('evaluacion', $requests['corregida']->status);
        $this->assertSame('borrador', $requests['borrador']->quotations->first()->status);
        $this->assertSame('emitida', $requests['emitida']->currentQuotation->status);
        $this->assertSame('vencida', $requests['vencida']->currentQuotation->status);
        $this->assertSame('sustituida', $requests['sustituida']->quotations()->orderBy('version')->first()->status);
        $this->assertSame('aceptada', $requests['aceptada']->closure_outcome);
        $this->assertSame('cerrada', $requests['aceptada']->status);
        $this->assertSame('rechazado', $requests['rechazada']->status);
        $this->assertSame('cancelado', $requests['cancelada']->status);
        $this->assertSame('rechazada', $requests['propuesta-rechazada']->currentQuotation->status);
        $this->assertSame('sin_acuerdo', $requests['cerrada']->closure_outcome);
        foreach ($requests as $request) {
            $this->assertStringEndsWith('@example.com', $request->client_email);
            $this->assertSame('000000000', $request->client_phone);
            $this->assertSame($request->estimated_cost, number_format((float) $request->area_m2 * (float) $request->base_price_m2 * (float) $request->lod_factor, 2, '.', ''));
            $previous = $request->created_at;
            foreach ($request->histories()->orderBy('id')->get() as $history) {
                $this->assertTrue($history->created_at->greaterThanOrEqualTo($previous));
                $previous = $history->created_at;
            }
        }
        foreach (Quotation::whereNotNull('pdf_path')->get() as $quote) {
            $pdf = Storage::disk('commercial')->get($quote->pdf_path);
            $this->assertStringStartsWith('%PDF-', $pdf);
            $this->assertSame($quote->pdf_hash, hash('sha256', $pdf));
            $this->assertTrue($quote->issued_at->lessThan($quote->expires_at));
            $this->assertSame('simulated', $quote->notification_status);
        }
        $this->assertFalse(WorkIntent::whereNotIn('status', ['done', 'cancelled'])->exists());
        $before = [DB::table('service_requests')->orderBy('id')->get()->toJson(), Quotation::get()->toJson(), AuditEvent::count()];
        $this->artisan('demo:prepare', $arguments)->assertSuccessful();
        $this->assertSame($before, [DB::table('service_requests')->orderBy('id')->get()->toJson(), Quotation::get()->toJson(), AuditEvent::count()]);
        $this->assertSame($account, hash('sha256', serialize($actor->fresh()->getRawOriginal())));
    }
}

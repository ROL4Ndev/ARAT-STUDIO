<?php

namespace Tests\Feature;

use App\Models\OfferedService;
use App\Services\QuotationCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CommercialFixtures;
use Tests\TestCase;

class DecimalCalculatorTest extends TestCase
{
    use CommercialFixtures,RefreshDatabase;

    public function test_decimal_rounding_boundary_and_api_numeric_contract(): void
    {
        $this->tariff();
        $c = app(QuotationCalculatorService::class);
        $this->assertSame('10800.00', $c->calculate('80', 'LOD_300')['estimated_cost_decimal']);
        $this->assertSame('67500.00', $c->calculate('500', 'LOD_300')['estimated_cost_decimal']);
        $this->assertNull($c->calculate('501', 'LOD_300')['estimated_cost']);
        $this->get('/cotizador')->assertOk()->assertSee('Solicitar proyecto');
        $this->postJson('/api/calcular-presupuesto', ['area_m2' => '80', 'lod_selected' => 'LOD_300'])->assertOk()->assertJsonPath('estimated_cost', 10800)->assertJsonPath('is_demo', true);
        $this->tariff('0.01', 'LOD_300', '1.500');
        $this->assertSame('0.02', $c->calculate('1', 'LOD_300')['estimated_cost_decimal']);
    }

    public function test_no_tariff_or_manual_never_uses_zero_or_silent_fallback(): void
    {
        $this->assertNull(app(QuotationCalculatorService::class)->calculate('80', 'LOD_300')['estimated_cost_decimal']);
        $s = OfferedService::where('code', 'BIM')->first();
        $s->update(['estimation_mode' => 'manual']);
        $result = app(QuotationCalculatorService::class)->calculate(null, null, $s);
        $this->assertNull($result['estimated_cost']);
        $this->assertStringContainsString('manual', $result['reason']);
    }
}

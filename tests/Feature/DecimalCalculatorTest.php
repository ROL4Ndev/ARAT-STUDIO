<?php

namespace Tests\Feature;

use App\Models\TariffParam;
use App\Services\QuotationCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DecimalCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_decimal_calculation_and_scale_boundary(): void
    {
        TariffParam::create(['lod_level' => 'LOD_300', 'base_price_m2' => '100.00', 'lod_factor' => '1.350', 'max_area_m2' => '500.00']);
        $calculator = app(QuotationCalculatorService::class);
        $this->assertSame('10800.00', $calculator->calculate('80', 'LOD_300')['estimated_cost_decimal']);
        $this->assertSame('67500.00', $calculator->calculate('500', 'LOD_300')['estimated_cost_decimal']);
        $this->assertNull($calculator->calculate('501', 'LOD_300')['estimated_cost']);
        TariffParam::query()->update(['base_price_m2' => '0.01', 'lod_factor' => '1.500']);
        $this->assertSame('0.02', $calculator->calculate('1', 'LOD_300')['estimated_cost_decimal']);
    }

    public function test_calculator_endpoint_remains_numeric_and_public_view_loads(): void
    {
        TariffParam::create(['lod_level' => 'LOD_300', 'base_price_m2' => '100.00', 'lod_factor' => '1.350', 'max_area_m2' => '500.00']);
        $this->get('/cotizador')->assertOk()->assertSee('Solicitar proyecto');
        $this->postJson('/api/calcular-presupuesto', ['area_m2' => '80', 'lod_selected' => 'LOD_300'])->assertOk()->assertJsonPath('estimated_cost', 10800)->assertJsonPath('area_m2', 80)->assertJsonPath('is_demo', true);
        $this->postJson('/api/calcular-presupuesto', ['area_m2' => '501', 'lod_selected' => 'LOD_300'])->assertOk()->assertJsonPath('estimated_cost', null)->assertJsonPath('out_of_scale', true);
    }
}

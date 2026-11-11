<?php

namespace App\Services;

use App\Models\TariffParam;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class QuotationCalculatorService
{
    public function calculate(string|int|float $area, string $lod): array
    {
        $tariff = TariffParam::query()
            ->where('lod_level', $lod)
            ->first();

        abort_if(
            $tariff === null,
            503,
            'No hay una tarifa disponible para este nivel.'
        );

        $decimalArea = BigDecimal::of((string) $area);
        $outOfScale = $decimalArea->isGreaterThan($tariff->max_area_m2);

        $amount = $outOfScale
            ? null
            : (string) $decimalArea->multipliedBy($tariff->base_price_m2)
                ->multipliedBy($tariff->lod_factor)->toScale(2, RoundingMode::HalfUp);

        return [
            'area_m2' => (float) $area,
            'lod_selected' => $tariff->lod_level,
            'base_price_m2' => $tariff->base_price_m2,
            'lod_factor' => $tariff->lod_factor,
            'max_area_m2' => $tariff->max_area_m2,
            'estimated_cost' => $amount === null ? null : (float) $amount,
            'estimated_cost_decimal' => $amount,
            'currency' => 'PEN',
            'out_of_scale' => $outOfScale,
            'is_demo' => true,
            'message' => $outOfScale
                ? 'El proyecto requiere una evaluación personalizada.'
                : 'Estimación referencial con tarifas de demostración.',
        ];
    }
}

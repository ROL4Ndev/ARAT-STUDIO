<?php

namespace App\Services;

use App\Models\TariffParam;

class QuotationCalculatorService
{
    public function calculate(float $area, string $lod): array
    {
        $tariff = TariffParam::query()
            ->where('lod_level', $lod)
            ->first();

        abort_if(
            $tariff === null,
            503,
            'No hay una tarifa disponible para este nivel.'
        );

        $outOfScale = $area > (float) $tariff->max_area_m2;

        $amount = $outOfScale
            ? null
            : round(
                $area
                * (float) $tariff->base_price_m2
                * (float) $tariff->lod_factor,
                2
            );

        return [
            'area_m2' => $area,
            'lod_selected' => $tariff->lod_level,
            'base_price_m2' => $tariff->base_price_m2,
            'lod_factor' => $tariff->lod_factor,
            'max_area_m2' => $tariff->max_area_m2,
            'estimated_cost' => $amount,
            'currency' => 'PEN',
            'out_of_scale' => $outOfScale,
            'is_demo' => true,
            'message' => $outOfScale
                ? 'El proyecto requiere una evaluación personalizada.'
                : 'Estimación referencial con tarifas de demostración.',
        ];
    }
}

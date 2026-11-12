<?php

namespace App\Services;

use App\Models\OfferedService;
use App\Models\TariffGroup;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class QuotationCalculatorService
{
    public function calculate(string|int|float|null $area, ?string $lod, ?OfferedService $service = null): array
    {
        $service ??= OfferedService::where('code', 'BIM')->firstOrFail();
        $manual = $service->estimation_mode === 'manual';
        $decimal = $area !== null && $area !== '' ? BigDecimal::of((string) $area)->toScale(2, RoundingMode::HalfUp) : null;
        $group = $lod ? TariffGroup::with('activeTariff')->where('lod_level', $lod)->first() : null;
        $tariff = $group?->activeTariff;
        if ($tariff && ($tariff->tariff_group_id !== $group->id || $tariff->status !== 'activa' || ! $tariff->is_active)) {
            $tariff = null;
        }
        $out = $decimal && $tariff ? $decimal->isGreaterThan($tariff->max_area_m2) : false;
        $reason = $manual ? 'Servicio con evaluación manual.' : (! $tariff ? 'No hay tarifa vigente disponible para este LOD.' : (! $decimal ? 'Completa el área y nivel LOD.' : ($out ? 'Área fuera del límite automático; requiere evaluación personalizada.' : null)));
        $amount = $reason ? null : (string) $decimal->multipliedBy($tariff->base_price_m2)->multipliedBy($tariff->lod_factor)->toScale(2, RoundingMode::HalfUp);
        $snapshot = ['id' => $manual ? null : $tariff?->id, 'version' => $manual ? null : $tariff?->version, 'base_price_m2' => $manual ? null : $tariff?->base_price_m2, 'lod_factor' => $manual ? null : $tariff?->lod_factor, 'max_area_m2' => $manual ? null : $tariff?->max_area_m2, 'currency' => 'PEN', 'estimated_cost' => $amount];
        $ss = ['code' => $service->code, 'name' => $service->name, 'description' => $service->description, 'estimation_mode' => $service->estimation_mode];

        return ['area_m2' => $decimal ? (float) (string) $decimal : null, 'lod_selected' => $lod, 'base_price_m2' => $snapshot['base_price_m2'], 'lod_factor' => $snapshot['lod_factor'], 'max_area_m2' => $snapshot['max_area_m2'], 'estimated_cost' => $amount === null ? null : (float) $amount, 'estimated_cost_decimal' => $amount, 'currency' => 'PEN', 'out_of_scale' => (bool) $out, 'custom_evaluation' => $amount === null, 'is_demo' => true, 'message' => $reason ?? 'Estimación referencial con tarifas demostrativas.', 'reason' => $reason, 'service_snapshot' => $ss, 'tariff_snapshot' => $snapshot, 'estimate_token' => hash_hmac('sha256', json_encode([$service->id, $ss, $snapshot, $decimal ? (string) $decimal : null, $lod]), config('app.key'))];
    }
}

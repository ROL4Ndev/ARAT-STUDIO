<?php

namespace Database\Seeders;

use App\Models\TariffParam;
use App\Models\TariffGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TariffParamSeeder extends Seeder
{
    public function run(): void
    {
        $tariffs = [
            ['lod_level' => 'LOD_200', 'lod_factor' => '1.000'],
            ['lod_level' => 'LOD_300', 'lod_factor' => '1.350'],
            ['lod_level' => 'LOD_400', 'lod_factor' => '1.750'],
        ];

        foreach ($tariffs as $tariff) {
            DB::transaction(function () use ($tariff) {
                $group = TariffGroup::firstOrCreate(['lod_level' => $tariff['lod_level']]);
                $group = TariffGroup::whereKey($group->id)->lockForUpdate()->firstOrFail();
                if ($group->active_tariff_id !== null || $group->versions()->exists()) {
                    return;
                }
                $version = TariffParam::create([
                    'tariff_group_id' => $group->id,
                    'version' => 1,
                    'lod_level' => $tariff['lod_level'],
                    'base_price_m2' => '100.00',
                    'lod_factor' => $tariff['lod_factor'],
                    'max_area_m2' => '500.00',
                    'status' => 'activa',
                    'is_active' => true,
                    'currency' => 'PEN',
                ]);
                $group->update(['active_tariff_id' => $version->id]);
            });
        }
    }
}

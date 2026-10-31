<?php

namespace Database\Seeders;

use App\Models\TariffParam;
use Illuminate\Database\Seeder;

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
            TariffParam::firstOrCreate(
                ['lod_level' => $tariff['lod_level']],
                [
                    'base_price_m2' => '100.00',
                    'lod_factor' => $tariff['lod_factor'],
                    'max_area_m2' => '500.00',
                ]
            );
        }
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TariffParam extends Model
{
    protected $fillable = [
        'lod_level',
        'base_price_m2',
        'lod_factor',
        'max_area_m2',
    ];

    protected function casts(): array
    {
        return [
            'base_price_m2' => 'decimal:2',
            'lod_factor' => 'decimal:3',
            'max_area_m2' => 'decimal:2',
        ];
    }
}

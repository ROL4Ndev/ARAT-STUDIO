<?php

namespace App\Models;

class TariffParam extends BusinessModel
{
    protected $fillable = [
        'lod_level',
        'base_price_m2',
        'lod_factor',
        'max_area_m2',
        'tariff_group_id', 'version', 'status', 'currency', 'version_reason', 'is_active', 'lock_version', 'status_reason', 'deactivated_at', 'deactivated_by',
    ];

    protected static function booted(): void
    {
        parent::booted();
        static::saving(function (self $tariff) {
            // Compatibilidad heredada: el booleano se deriva siempre del estado.
            if ($tariff->status !== null) {
                $tariff->is_active = $tariff->status === 'activa';
            }
        });
        static::updating(function (self $tariff) {
            if ($tariff->getRawOriginal('status') !== 'borrador' && $tariff->isDirty(['lod_level', 'tariff_group_id', 'version', 'base_price_m2', 'lod_factor', 'max_area_m2', 'currency'])) {
                throw new \LogicException('Los valores publicados requieren una nueva versión de tarifa.');
            }
        });
    }

    protected function casts(): array
    {
        return [...parent::casts(),
            'base_price_m2' => 'decimal:2',
            'lod_factor' => 'decimal:3',
            'max_area_m2' => 'decimal:2',
        ];
    }

    public function group()
    {
        return $this->belongsTo(TariffGroup::class, 'tariff_group_id');
    }
}

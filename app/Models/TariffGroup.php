<?php

namespace App\Models;

class TariffGroup extends BusinessModel
{
    public function activeTariff()
    {
        return $this->belongsTo(TariffParam::class, 'active_tariff_id');
    }

    public function versions()
    {
        return $this->hasMany(TariffParam::class);
    }
}

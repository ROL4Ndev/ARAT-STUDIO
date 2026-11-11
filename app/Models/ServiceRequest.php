<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServiceRequest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['area_m2' => 'decimal:2', 'estimated_cost' => 'decimal:2', 'base_price_m2' => 'decimal:2', 'lod_factor' => 'decimal:3', 'max_area_m2' => 'decimal:2', 'custom_evaluation' => 'boolean', 'consented_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ServiceRequestHistory::class);
    }

    public function quotation(): HasOne
    {
        return $this->hasOne(Quotation::class);
    }
}

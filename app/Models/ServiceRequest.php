<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceRequest extends BusinessModel
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [...parent::casts(), 'closed_at' => UtcDateTime::class, 'service_snapshot' => 'array', 'tariff_snapshot' => 'array', 'first_evaluated_at' => UtcDateTime::class, 'area_m2' => 'decimal:2', 'estimated_cost' => 'decimal:2', 'base_price_m2' => 'decimal:2', 'lod_factor' => 'decimal:3', 'max_area_m2' => 'decimal:2', 'custom_evaluation' => 'boolean', 'consented_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ServiceRequestHistory::class);
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class);
    }

    public function currentQuotation()
    {
        return $this->belongsTo(Quotation::class, 'current_quotation_id');
    }

    public function currentRevision()
    {
        return $this->belongsTo(RequestRevision::class, 'current_revision_id');
    }

    public function revisions()
    {
        return $this->hasMany(RequestRevision::class);
    }

    public function files()
    {
        return $this->hasMany(RequestFile::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function evaluations()
    {
        return $this->hasMany(TechnicalEvaluation::class);
    }
}

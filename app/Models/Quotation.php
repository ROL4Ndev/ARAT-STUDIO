<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quotation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['client_snapshot' => 'array', 'studio_snapshot' => 'array', 'official_amount' => 'decimal:2', 'issued_at' => 'date', 'expires_at' => 'date'];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->lt(today());
    }
}

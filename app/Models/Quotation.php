<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quotation extends BusinessModel
{
    protected $attributes = ['lock_version' => 0];

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        parent::booted();
        static::updating(function (self $quotation) {
            $content = ['service_request_id', 'code', 'version', 'user_id', 'request_revision_id', 'official_amount', 'currency', 'scope', 'deliverables', 'exclusions', 'execution_term', 'adjustment_reason', 'version_reason', 'client_snapshot', 'studio_snapshot', 'price_snapshot'];
            if ($quotation->getRawOriginal('status') !== 'borrador' && $quotation->isDirty($content)) {
                throw new \LogicException('El contenido congelado no se edita; crea otra versión.');
            }
            if ($quotation->getRawOriginal('issued_at') !== null && $quotation->isDirty(['issued_at', 'expires_at', 'pdf_path', 'pdf_hash'])) {
                throw new \LogicException('El PDF y las fechas de una versión emitida son inmutables.');
            }
        });
    }

    protected function casts(): array
    {
        return [...parent::casts(), 'client_snapshot' => 'array', 'studio_snapshot' => 'array', 'price_snapshot' => 'array', 'official_amount' => 'decimal:2', 'issued_at' => UtcDateTime::class, 'expires_at' => UtcDateTime::class, 'responded_at' => UtcDateTime::class];
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
        return $this->expires_at !== null && now('UTC')->greaterThanOrEqualTo($this->expires_at);
    }
}

<?php

namespace App\Models;

use App\Casts\UtcDateTime;

class AuditEvent extends BusinessModel
{
    public $timestamps = false;

    protected static function booted(): void
    {
        parent::booted();
        static::updating(fn () => throw new \LogicException('La auditoría es inmutable.'));
    }

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'occurred_at' => UtcDateTime::class];
    }
}

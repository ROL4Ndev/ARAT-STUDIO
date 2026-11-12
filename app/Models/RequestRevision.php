<?php

namespace App\Models;

class RequestRevision extends BusinessModel
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        parent::booted();
        static::updating(fn () => throw new \LogicException('Las revisiones se conservan inmutables.'));
    }

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array'];
    }
}

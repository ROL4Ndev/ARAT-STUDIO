<?php

namespace App\Models;

use App\Casts\UtcDateTime;

class NotificationAttempt extends BusinessModel
{
    public $timestamps = false;

    protected static function booted(): void
    {
        parent::booted();
        static::updating(function (self $attempt) {
            if ($attempt->getRawOriginal('status') !== 'processing' || $attempt->isDirty(['notification_record_id', 'attempt_no', 'destination', 'attempted_at'])) {
                throw new \LogicException('Un intento finalizado de notificación es inmutable.');
            }
        });
    }

    protected function casts(): array
    {
        return ['attempted_at' => UtcDateTime::class];
    }
}

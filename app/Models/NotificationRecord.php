<?php

namespace App\Models;

class NotificationRecord extends BusinessModel
{
    public function deliveries()
    {
        return $this->hasMany(NotificationAttempt::class);
    }
}

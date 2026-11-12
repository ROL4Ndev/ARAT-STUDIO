<?php

namespace App\Models;

use App\Casts\UtcDateTime;

class WorkIntent extends BusinessModel
{
    protected function casts(): array
    {
        return ['result' => 'array', 'leased_at' => UtcDateTime::class];
    }
}

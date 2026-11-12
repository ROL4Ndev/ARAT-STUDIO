<?php

namespace App\Models;

use App\Casts\UtcDateTime;

class ClientAccessToken extends BusinessModel
{
    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['expires_at' => UtcDateTime::class, 'consumed_at' => UtcDateTime::class];
    }
}

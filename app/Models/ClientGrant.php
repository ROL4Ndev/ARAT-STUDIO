<?php

namespace App\Models;

use App\Casts\UtcDateTime;

class ClientGrant extends BusinessModel
{
    protected $hidden = ['session_hash'];

    protected function casts(): array
    {
        return ['expires_at' => UtcDateTime::class, 'revoked_at' => UtcDateTime::class];
    }

    public function serviceRequest()
    {
        return $this->belongsTo(ServiceRequest::class);
    }
}

<?php

namespace App\Models;

class TechnicalEvaluation extends BusinessModel
{
    public const UPDATED_AT = null;

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

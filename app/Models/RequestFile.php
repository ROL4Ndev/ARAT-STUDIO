<?php

namespace App\Models;

class RequestFile extends BusinessModel
{
    public const UPDATED_AT = null;

    public function serviceRequest()
    {
        return $this->belongsTo(ServiceRequest::class);
    }
}

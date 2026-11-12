<?php

namespace App\Models;

class OfferedService extends BusinessModel
{
    protected $table = 'services';

    protected static function booted(): void
    {
        parent::booted();
        static::updating(function (self $service) {
            if ($service->isDirty('code')) {
                throw new \LogicException('El código de servicio es inmutable.');
            }
        });
    }

    public function requests()
    {
        return $this->hasMany(ServiceRequest::class, 'service_id');
    }
}

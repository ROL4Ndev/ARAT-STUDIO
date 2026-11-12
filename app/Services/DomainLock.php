<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

class DomainLock
{
    public static function get(Model $record, int $version): Model
    {
        $locked = $record->newQuery()->whereKey($record->id)->lockForUpdate()->firstOrFail();
        abort_if($locked->lock_version !== $version, 409, 'El registro cambió. Recarga la página antes de confirmar otra operación.');

        return $locked;
    }
}

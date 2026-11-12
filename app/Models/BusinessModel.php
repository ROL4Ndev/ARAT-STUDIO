<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

abstract class BusinessModel extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new \LogicException('Los registros de negocio se conservan; utiliza desactivación.'));
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'lock_version' => 'integer', 'deactivated_at' => 'datetime'];
    }
}

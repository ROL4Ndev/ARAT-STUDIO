<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends BusinessModel
{
    protected $fillable = [
        'name',
        'slug',
        'description', 'is_active', 'lock_version', 'status_reason', 'deactivated_at', 'deactivated_by',
    ];

    protected static function booted(): void
    {
        parent::booted();
        static::updating(function (self $category) {
            if ($category->isDirty('slug')) {
                throw new \LogicException('El slug publicado se conserva.');
            }
        });
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}

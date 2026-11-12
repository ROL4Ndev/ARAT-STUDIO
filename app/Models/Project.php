<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Project extends BusinessModel
{
    protected $fillable = [
        'title',
        'slug',
        'category_id',
        'description',
        'cover_image',
        'is_active',
        'lock_version', 'status_reason', 'deactivated_at', 'deactivated_by',
    ];

    protected function casts(): array
    {
        return [...parent::casts(),
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        parent::booted();
        static::updating(function (self $project) {
            if ($project->isDirty('slug')) {
                throw new \LogicException('El slug publicado se conserva.');
            }
        });
    }

    public function projectCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function coverHistories()
    {
        return $this->hasMany(ProjectCoverHistory::class);
    }

    public function getCoverImageUrlAttribute(): string
    {
        if (Str::startsWith($this->cover_image, 'img/')) {
            return asset($this->cover_image);
        }

        return Storage::disk('public')->url($this->cover_image);
    }
}

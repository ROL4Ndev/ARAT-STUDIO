<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Project extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'category_id',
        'description',
        'cover_image',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function projectCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function getCoverImageUrlAttribute(): string
    {
        if (Str::startsWith($this->cover_image, 'img/')) {
            return asset($this->cover_image);
        }

        return Storage::disk('public')->url($this->cover_image);
    }
}

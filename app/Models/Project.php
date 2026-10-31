<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Project extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'category',
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
}
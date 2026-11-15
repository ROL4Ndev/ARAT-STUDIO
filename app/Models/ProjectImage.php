<?php

namespace App\Models;

use Illuminate\Support\Facades\Storage;

class ProjectImage extends BusinessModel
{
    protected function casts(): array
    {
        return [...parent::casts(), 'sort_order' => 'integer', 'width' => 'integer', 'height' => 'integer'];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function getUrlAttribute(): string
    {
        return str_starts_with($this->path, 'img/') ? asset($this->path) : Storage::disk('public')->url($this->path);
    }

    public function getThumbnailUrlAttribute(): string
    {
        return $this->thumbnail_path ? Storage::disk('public')->url($this->thumbnail_path) : $this->url;
    }
}

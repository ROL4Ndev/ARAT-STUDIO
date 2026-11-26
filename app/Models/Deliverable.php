<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Deliverable extends BusinessModel
{
    protected $attributes = ['lock_version' => 0, 'status' => 'borrador'];

    protected static function booted(): void
    {
        parent::booted();
        static::updating(function (self $record) {
            if ($record->getRawOriginal('status') === 'conforme' && $record->isDirty()) {
                throw new \LogicException('Un entregable conforme es de solo lectura.');
            }
            if ($record->isDirty(['quotation_id', 'creation_key', 'creation_hash', 'code'])) {
                throw new \LogicException('La vinculación del entregable se conserva.');
            }
        });
    }

    public function getRouteKeyName(): string { return 'code'; }
    public function quotation(): BelongsTo { return $this->belongsTo(Quotation::class); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function versions(): HasMany { return $this->hasMany(DeliverableVersion::class)->orderBy('number'); }
    public function latestVersion(): HasOne { return $this->hasOne(DeliverableVersion::class)->ofMany('number', 'max'); }
    public function publishedVersion(): HasOne
    {
        return $this->hasOne(DeliverableVersion::class)->ofMany(['number' => 'max'], fn (Builder $q) => $q->whereNotNull('sent_at'));
    }
}

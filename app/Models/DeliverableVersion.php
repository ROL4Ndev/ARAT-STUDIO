<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliverableVersion extends BusinessModel
{
    protected static function booted(): void
    {
        parent::booted();
        static::updating(function (self $version) {
            if ($version->getRawOriginal('sent_at') !== null && $version->isDirty([
                'deliverable_id', 'number', 'title', 'description', 'internal_note', 'path', 'original_name',
                'mime', 'bytes', 'sha256', 'user_id', 'sent_at', 'sent_by', 'save_key', 'content_hash',
            ])) {
                throw new \LogicException('El contenido de una versión enviada es inmutable.');
            }
            if ($version->getRawOriginal('responded_at') !== null && $version->isDirty()) {
                throw new \LogicException('La respuesta registrada se conserva.');
            }
        });
    }

    protected function casts(): array
    {
        return [...parent::casts(), 'number' => 'integer', 'bytes' => 'integer', 'sent_at' => UtcDateTime::class, 'responded_at' => UtcDateTime::class];
    }

    public function deliverable(): BelongsTo { return $this->belongsTo(Deliverable::class); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function sender(): BelongsTo { return $this->belongsTo(User::class, 'sent_by'); }
    public function clientGrant(): BelongsTo { return $this->belongsTo(ClientGrant::class); }
}

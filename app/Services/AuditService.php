<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\ClientGrant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditService
{
    public function record(Model $record, string $action, string $reason, array $before = [], array $after = [], User|ClientGrant|null $actor = null): void
    {
        $clean = function (array $data) use (&$clean): array {
            $result = [];
            foreach ($data as $key => $value) {
                if (preg_match('/password|token|secret|credential|session_hash|lease/i', (string) $key)) {
                    continue;
                }
                $result[$key] = is_array($value) ? $clean($value) : $value;
            }

            return $result;
        };
        AuditEvent::create(['actor_id' => $actor instanceof User ? $actor->id : null, 'actor_type' => $actor instanceof ClientGrant ? 'client:'.$actor->id : ($actor ? 'staff' : 'system'), 'resource_type' => $record->getMorphClass(), 'resource_id' => $record->id, 'action' => $action, 'reason' => $reason, 'before' => $clean($before), 'after' => $clean($after), 'occurred_at' => now('UTC')]);
    }
}

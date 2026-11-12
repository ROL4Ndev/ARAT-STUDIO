<?php

namespace App\Services;

use App\Models\NotificationRecord;
use App\Models\WorkIntent;

class NotificationService
{
    public function queue(string $kind, int $id, string $recipient): NotificationRecord
    {
        $notice = NotificationRecord::firstOrCreate(['kind' => $kind, 'resource_id' => $id, 'recipient' => $recipient]);
        WorkIntent::firstOrCreate(['key' => 'notification:'.$notice->id], ['kind' => 'notification', 'resource_id' => $notice->id, 'status' => 'pending']);

        return $notice;
    }
}

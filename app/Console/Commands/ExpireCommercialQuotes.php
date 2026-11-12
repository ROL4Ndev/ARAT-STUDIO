<?php

namespace App\Console\Commands;

use App\Models\Quotation;
use App\Models\ServiceRequest;
use App\Services\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireCommercialQuotes extends Command
{
    protected $signature = 'commercial:expire';

    protected $description = 'Marca vencimientos sin sustituir la validación al responder.';

    public function handle(): int
    {
        foreach (Quotation::where('status', 'emitida')->where('expires_at', '<=', now('UTC'))->pluck('id') as $id) {
            DB::transaction(function () use ($id) {
                $candidate = Quotation::findOrFail($id);
                ServiceRequest::whereKey($candidate->service_request_id)->lockForUpdate()->firstOrFail();
                $q = Quotation::whereKey($id)->lockForUpdate()->firstOrFail();
                if ($q->status === 'emitida' && $q->isExpired()) {
                    $before = $q->toArray();
                    $q->update(['status' => 'vencida', 'lock_version' => $q->lock_version + 1]);
                    app(AuditService::class)->record($q, 'quotation_expired', 'Fin del plazo de vigencia', $before, $q->toArray());
                }
            }, 3);
        }
        $this->info('Vencimientos revisados.');

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Jobs\ProcessWorkIntent;
use App\Models\WorkIntent;
use App\Services\WorkProcessor;
use Illuminate\Console\Command;

class RecoverCommercialWork extends Command
{
    protected $signature = 'commercial:recover {--inline : Procesar en este proceso} {--limit=50}';

    protected $description = 'Recupera intenciones durables pendientes y leases abandonados, sin duplicar versiones.';

    public function handle(): int
    {
        WorkIntent::where('status', 'processing')->where('leased_at', '<', now('UTC')->subMinutes(5))->update(['status' => 'pending', 'lease' => null, 'leased_at' => null]);
        $ids = WorkIntent::where('status', 'pending')->orderBy('id')->limit(min(200, max(1, (int) $this->option('limit'))))->pluck('id');
        foreach ($ids as $id) {
            if ($this->option('inline')) {
                app(WorkProcessor::class)->run($id);
            } else {
                ProcessWorkIntent::dispatch($id);
            }
        }
        $this->info('Intenciones revisadas: '.$ids->count());

        return self::SUCCESS;
    }
}

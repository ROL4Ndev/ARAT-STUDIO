<?php

namespace App\Console\Commands;

use App\Models\WorkIntent;
use Illuminate\Console\Command;

class RetryCommercialWork extends Command
{
    protected $signature = 'commercial:retry {intent : Identificador técnico de la intención fallida}';

    protected $description = 'Vuelve a poner en cola una intención fallida; conserva la versión y sus archivos.';

    public function handle(): int
    {
        $n = WorkIntent::whereKey($this->argument('intent'))->where('status', 'failed')->update(['status' => 'pending', 'lease' => null, 'leased_at' => null]);
        if (! $n) {
            $this->error('No hay una intención fallida con ese identificador.');

            return self::FAILURE;
        }
        $this->info('Reintento preparado; ejecutar commercial:recover.');

        return self::SUCCESS;
    }
}

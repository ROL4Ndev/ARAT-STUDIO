<?php

namespace App\Console\Commands;

use App\Services\PortfolioInventory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InventoryPortfolio extends Command
{
    protected $signature = 'portfolio:inventario {--output= : Archivo JSON de salida}';

    protected $description = 'Inventariar fotografías y coincidencias de proyectos sin escribir en la base.';

    public function handle(PortfolioInventory $inventory): int
    {
        $report = $inventory->inspect();
        if ($path = $this->option('output')) {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        $this->info($report['folder_count'].' carpetas · '.$report['image_count'].' imágenes · '.$report['database_status']);
        $this->table(['Carpeta', 'Imágenes', 'Coincidencias', 'Resolución'], array_map(fn ($group) => [$group['folder'], count($group['images']), count($group['matches']), $group['resolution']], $report['groups']));

        return self::SUCCESS;
    }
}

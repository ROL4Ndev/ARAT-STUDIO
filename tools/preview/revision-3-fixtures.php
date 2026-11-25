<?php

use App\Models\Project;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$app = require __DIR__.'/bootstrap.php';
$app->make(Kernel::class)->bootstrap();
$database = str_replace('\\', '/', config('database.connections.sqlite.database'));
if (DB::getDriverName() !== 'sqlite' || ! str_contains($database, '/design-preview-v3/demo.sqlite')) {
    throw new RuntimeException('Solo se permite preparar el SQLite aislado de esta revisión.');
}
// Reproducir el estado editorial real: todos publicados, selección inicial pendiente del administrador.
Project::whereIn('slug', ['proyecto-sala-t', 'proyecto-opv', 'departamento-mr'])->update(['is_featured' => false]);
echo 'Solo demostración: selección inicial vacía, sin cambiar publicación ni galerías.'.PHP_EOL;

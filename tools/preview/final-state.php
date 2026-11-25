<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$app = require __DIR__.'/bootstrap.php';
$app->make(Kernel::class)->bootstrap();
if (DB::getDriverName() !== 'sqlite' || ! str_contains(str_replace('\\', '/', DB::connection()->getDatabaseName()), '/design-preview-final/')) {
    throw new RuntimeException('Solo se consulta el SQLite final.');
}
$path = storage_path('state.json');
$state = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
$state['scenarios'] = DB::table('service_requests')->whereNotNull('demo_scenario')->pluck('code', 'demo_scenario')->all();
file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT));
echo 'Índice privado de escenarios actualizado.'.PHP_EOL;

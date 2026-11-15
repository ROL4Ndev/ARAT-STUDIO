<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

// El bootstrap fija SQLite, correo array y almacenamiento aislado antes de arrancar Laravel.
$app = require __DIR__.'/bootstrap.php';
$app->make(Kernel::class)->bootstrap();
$until = microtime(true) + 240;
while (microtime(true) < $until) {
    Artisan::call('commercial:recover', ['--inline' => true]);
    usleep(500000);
}
echo 'Procesamiento de demostración finalizado.'.PHP_EOL;

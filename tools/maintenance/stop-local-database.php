<?php

use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = config('database.connections.mysql');
if (($argv[1] ?? '') !== '--stop-owned' || ! $app->environment('local')
    || ! in_array($connection['host'], ['127.0.0.1', 'localhost'], true) || $connection['database'] !== 'arat_studios') {
    throw new RuntimeException('Solo se detiene el servidor local iniciado para esta intervención, tras verificar su PID.');
}
$process = new Process(['C:/xampp/mysql/bin/mysqladmin.exe', '--host='.$connection['host'], '--port='.(string) $connection['port'],
    '--user='.$connection['username'], 'shutdown'], base_path(), ['MYSQL_PWD' => (string) $connection['password']]);
$process->setTimeout(30)->mustRun();
echo 'MariaDB local detenido de forma ordenada.'.PHP_EOL;

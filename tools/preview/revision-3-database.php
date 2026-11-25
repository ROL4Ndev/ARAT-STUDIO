<?php

// Herramienta manual: consulta y respaldo de la base habitual, sin mostrar credenciales.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (DB::getDriverName() !== 'mysql' || DB::connection()->getDatabaseName() !== 'arat_studios') {
    throw new RuntimeException('Se esperaba exclusivamente la conexión habitual arat_studios.');
}
$directory = storage_path('app/revision-3');
if (! is_dir($directory)) {
    mkdir($directory, 0700, true);
}
$snapshot = [
    'projects' => DB::table('projects')->orderBy('id')->get()->toArray(),
    'categories' => DB::table('categories')->orderBy('id')->get(['id', 'name', 'slug', 'is_active'])->toArray(),
    'services' => DB::table('services')->orderBy('id')->get(['id', 'code', 'name', 'description', 'is_active'])->toArray(),
    'admin_ids' => DB::table('users')->where('role', 'admin')->where('is_active', true)->pluck('id')->all(),
    'table_counts' => [],
];
foreach (DB::select('SHOW TABLES') as $row) {
    $table = array_values((array) $row)[0];
    $snapshot['table_counts'][$table] = DB::table($table)->count();
}
$file = $directory.'/database-before.json';
if (! is_file($file)) {
    file_put_contents($file, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
if (($argv[1] ?? '') === '--backup') {
    $backup = $directory.'/arat-studios-before-portfolio.sql';
    if (is_file($backup)) {
        throw new RuntimeException('Ya existe el respaldo; no se sobrescribe.');
    }
    $connection = config('database.connections.mysql');
    $process = new Process([
        'C:/xampp/mysql/bin/mysqldump.exe', '--single-transaction', '--quick', '--skip-lock-tables',
        '--routines', '--events', '--triggers', '--hex-blob', '--default-character-set=utf8mb4',
        '--host='.$connection['host'], '--port='.(string) $connection['port'], '--user='.$connection['username'],
        '--result-file='.$backup, $connection['database'],
    ], base_path(), ['MYSQL_PWD' => (string) $connection['password']]);
    $process->setTimeout(120)->run();
    if (! $process->isSuccessful() || ! is_file($backup) || filesize($backup) < 1000) {
        throw new RuntimeException('El respaldo no se confirmó. No se aplicarán cambios a la base.');
    }
    file_put_contents($directory.'/backup-receipt.json', json_encode(['file' => basename($backup), 'bytes' => filesize($backup), 'sha256' => hash_file('sha256', $backup), 'created_at' => now('America/Lima')->toIso8601String()], JSON_PRETTY_PRINT));
    echo 'Respaldo completo guardado en storage privado e ignorado; bytes: '.filesize($backup).PHP_EOL;
}
echo json_encode(['categories' => $snapshot['categories'], 'services' => $snapshot['services'], 'admin_ids' => $snapshot['admin_ids'], 'projects' => $snapshot['projects']], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;

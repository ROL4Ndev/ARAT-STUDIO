<?php

// Respaldo local privado. La restauración nunca apunta a la base de trabajo.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (DB::getDriverName() !== 'mysql' || DB::connection()->getDatabaseName() !== 'arat_studios') {
    throw new RuntimeException('Solo se respalda la conexión local arat_studios.');
}
$directory = storage_path('app/private/final-maintenance');
$receiptFile = $directory.'/backup-receipt.json';
if (is_file($receiptFile)) {
    throw new RuntimeException('El respaldo existente se conserva; no se sobrescribe.');
}
if (! is_dir($directory)) {
    mkdir($directory, 0700, true);
}
$connection = config('database.connections.mysql');
$arguments = ['--host='.$connection['host'], '--port='.(string) $connection['port'], '--user='.$connection['username'], '--default-character-set=utf8mb4'];
$environment = ['MYSQL_PWD' => (string) $connection['password']];
function fingerprints(): array
{
    $result = [];
    foreach (DB::select('SHOW TABLES') as $row) {
        $table = array_values((array) $row)[0];
        $keys = array_map(fn ($key) => $key->Column_name, DB::select('SHOW KEYS FROM `'.$table.'` WHERE Key_name = ?', ['PRIMARY']));
        $query = DB::table($table);
        foreach ($keys as $key) {
            $query->orderBy($key);
        }
        $rows = $query->get()->map(fn ($value) => (array) $value)->all();
        $result[$table] = ['count' => count($rows), 'sha256' => hash('sha256', serialize($rows))];
    }

    return $result;
}
$before = fingerprints();
$dump = $directory.'/arat-studios.sql';
$process = new Process(['C:/xampp/mysql/bin/mysqldump.exe', ...$arguments, '--single-transaction', '--quick', '--routines', '--events', '--triggers', '--hex-blob', '--result-file='.$dump, $connection['database']], base_path(), $environment);
$process->setTimeout(180)->mustRun();
$zip = new ZipArchive;
if ($zip->open($directory.'/related-files.zip', ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
    throw new RuntimeException('No se pudo crear el archivo de respaldo.');
}
$files = [];
foreach (['public/img', 'public/media', 'storage/app/commercial', 'storage/app/public', 'storage/app/private', 'storage/app/revision-3/media', 'resources', 'config', 'app', 'routes', 'database', 'tests', 'tools'] as $folder) {
    $root = base_path($folder);
    if (! is_dir($root)) {
        continue;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        $path = str_replace('\\', '/', $file->getPathname());
        if (! $file->isFile() || $file->isLink() || str_contains($path, '/private/final-maintenance/')) {
            continue;
        }
        $relative = substr($path, strlen(str_replace('\\', '/', base_path())) + 1);
        $files[$relative] = hash_file('sha256', $path);
        $zip->addFile($path, $relative);
    }
}
foreach (['.env', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json', 'README.md'] as $file) {
    $files[$file] = hash_file('sha256', base_path($file));
    $zip->addFile(base_path($file), $file);
}
$zip->close();
if (fingerprints() !== $before) {
    throw new RuntimeException('La base cambió durante el respaldo; detén los procesos antes de repetirlo.');
}
$restore = 'arat_restore_final_'.date('Ymd_His');
if (! preg_match('/^arat_restore_final_[0-9_]+$/', $restore)) {
    throw new RuntimeException('Nombre de restauración no válido.');
}
DB::statement('CREATE DATABASE `'.$restore.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$process = new Process(['C:/xampp/mysql/bin/mysql.exe', ...$arguments, '--binary-mode', '--database='.$restore], base_path(), $environment);
$input = fopen($dump, 'rb');
$process->setInput($input)->setTimeout(180)->mustRun();
fclose($input);
config(['database.connections.mysql.database' => $restore]);
DB::purge('mysql');
if (fingerprints() !== $before) {
    throw new RuntimeException('El contenido restaurado no coincide con la base original.');
}
$restoredFiles = $directory.'/restore-files';
$zip->open($directory.'/related-files.zip');
if (! $zip->extractTo($restoredFiles)) {
    throw new RuntimeException('No se pudo restaurar el archivo ZIP.');
}
$zip->close();
foreach ($files as $file => $hash) {
    if (hash_file('sha256', $restoredFiles.'/'.$file) !== $hash) {
        throw new RuntimeException('Un archivo restaurado no coincide.');
    }
}
$receipt = ['database' => $connection['database'], 'restore_database' => $restore, 'restored_at' => now('America/Lima')->toIso8601String(),
    'sql_sha256' => hash_file('sha256', $dump), 'zip_sha256' => hash_file('sha256', $directory.'/related-files.zip'),
    'tables' => $before, 'files' => $files, 'restore_verified' => true];
file_put_contents($receiptFile, json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode(['backup' => $directory, 'restored_database' => $restore, 'tables_verified' => count($before), 'files_verified' => count($files), 'restored' => true]).PHP_EOL;

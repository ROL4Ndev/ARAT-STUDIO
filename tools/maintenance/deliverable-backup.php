<?php

// Respaldo específico de esta entrega. Nunca sustituye la base habitual al verificar la restauración.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = config('database.connections.mysql');
if (($argv[1] ?? '') !== '--backup' || ! app()->environment('local') || DB::getDriverName() !== 'mysql'
    || DB::connection()->getDatabaseName() !== 'arat_studios' || ! in_array($connection['host'], ['127.0.0.1', 'localhost'], true)) {
    throw new RuntimeException('Solo --backup para la instalación local conocida.');
}
$folder = storage_path('app/private/third-presentation/'.gmdate('Ymd_His'));
if (is_dir($folder)) throw new RuntimeException('El respaldo ya existe; no se sobrescribe.');
mkdir($folder, 0700, true);
$rows = [];
foreach (DB::select('SHOW TABLES') as $tableRow) {
    $table = array_values((array) $tableRow)[0];
    $tableRows = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
    $rows[$table] = array_map(fn ($row) => ['id' => $row['id'] ?? null, 'sha256' => hash('sha256', serialize($row))], $tableRows);
}
$dump = new Process(['C:/xampp/mysql/bin/mysqldump.exe', '--single-transaction', '--quick', '--skip-lock-tables',
    '--routines', '--events', '--triggers', '--hex-blob', '--default-character-set=utf8mb4',
    '--host='.$connection['host'], '--port='.(string) $connection['port'], '--user='.$connection['username'],
    '--result-file='.$folder.'/database.sql', 'arat_studios'], base_path(), ['MYSQL_PWD' => (string) $connection['password']]);
$dump->setTimeout(120)->mustRun();
$zip = new ZipArchive;
if ($zip->open($folder.'/files.zip', ZipArchive::CREATE | ZipArchive::EXCL) !== true) throw new RuntimeException('No se pudo crear el archivo del respaldo.');
$files = [];
foreach (['commercial' => Storage::disk('commercial')->path(''), 'public-files' => storage_path('app/public'),
    'visuals/img' => public_path('img'), 'visuals/media' => public_path('media')] as $prefix => $source) {
    if (! is_dir($source)) continue;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)) as $file) {
        if (! $file->isFile() || $file->isLink()) continue;
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(rtrim($source, '/\\')) + 1));
        $entry = $prefix.'/'.$relative;
        $zip->addFile($file->getPathname(), $entry);
        $files[$entry] = ['source' => $file->getPathname(), 'sha256' => hash_file('sha256', $file->getPathname())];
    }
}
$zip->addFile(base_path('.env'), 'configuration/.env');
$files['configuration/.env'] = ['source' => base_path('.env'), 'sha256' => hash_file('sha256', base_path('.env'))];
$zip->close();
$restore = 'arat_delivery_restore_'.gmdate('Ymd_His');
DB::statement('CREATE DATABASE `'.$restore.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$load = new Process(['C:/xampp/mysql/bin/mysql.exe', '--binary-mode', '--host='.$connection['host'],
    '--port='.(string) $connection['port'], '--user='.$connection['username'], '--database='.$restore], base_path(), ['MYSQL_PWD' => (string) $connection['password']]);
$input = fopen($folder.'/database.sql', 'rb');
try { $load->setInput($input)->setTimeout(120)->mustRun(); } finally { fclose($input); }
config(['database.connections.deliverable_restore' => array_replace($connection, ['database' => $restore, 'url' => null])]);
$restored = DB::connection('deliverable_restore');
foreach ($rows as $table => $original) {
    $actual = $restored->table($table)->get()->map(fn ($row) => hash('sha256', serialize((array) $row)))->all();
    $expected = array_column($original, 'sha256');
    sort($actual); sort($expected);
    if ($actual !== $expected) throw new RuntimeException('La restauración SQL no coincide: '.$table);
}
$zip = new ZipArchive;
$zip->open($folder.'/files.zip');
$zip->extractTo($folder.'/restore-files');
$zip->close();
foreach ($files as $entry => $file) {
    if (hash_file('sha256', $folder.'/restore-files/'.$entry) !== $file['sha256']) throw new RuntimeException('No coincide un archivo restaurado.');
}
$receipt = ['database' => 'arat_studios', 'created_at' => now('America/Lima')->toIso8601String(), 'restore_database' => $restore,
    'restore_verified' => true, 'sql_sha256' => hash_file('sha256', $folder.'/database.sql'), 'zip_sha256' => hash_file('sha256', $folder.'/files.zip'),
    'rows' => $rows, 'files' => $files];
file_put_contents($folder.'/receipt.json', json_encode($receipt, JSON_PRETTY_PRINT));
file_put_contents(storage_path('app/private/third-presentation/latest.json'), json_encode(['folder' => basename($folder)]));
echo json_encode(['backup' => str_replace(base_path().DIRECTORY_SEPARATOR, '', $folder), 'tables' => count($rows), 'files' => count($files), 'restore_verified' => true], JSON_UNESCAPED_SLASHES).PHP_EOL;

<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$directory = storage_path('app/private/final-maintenance');
$receipt = json_decode(file_get_contents($directory.'/backup-receipt.json'), true, flags: JSON_THROW_ON_ERROR);
if (! preg_match('/^arat_restore_final_[0-9_]+$/', $receipt['restore_database']) || ! $receipt['restore_verified']) {
    throw new RuntimeException('Restauración no verificada.');
}
config(['database.connections.mysql.database' => $receipt['restore_database'],
    'filesystems.disks.commercial.root' => $directory.'/restore-files/storage/app/commercial',
    'cache.default' => 'array', 'mail.default' => 'array', 'queue.default' => 'sync']);
DB::purge('mysql');
if (DB::connection()->getDatabaseName() === 'arat_studios') {
    throw new RuntimeException('No se permite ensayar en la base habitual.');
}
Artisan::call('migrate', ['--force' => true]);
echo Artisan::output();
$arguments = ['--batch' => 'arat-2026-v1', '--seed' => '20261124', '--date' => '2026-11-24', '--actor' => 1, '--apply' => true];
if (Artisan::call('demo:prepare', $arguments) !== 0) {
    throw new RuntimeException(Artisan::output());
}
echo Artisan::output();
$first = DB::table('service_requests')->where('demo_batch', 'arat-2026-v1')->orderBy('id')->get()->toJson();
$quotes = DB::table('quotations')->orderBy('id')->get()->toJson();
$auditCount = DB::table('audit_events')->count();
Artisan::call('demo:prepare', $arguments);
if ($first !== DB::table('service_requests')->where('demo_batch', 'arat-2026-v1')->orderBy('id')->get()->toJson()
    || $quotes !== DB::table('quotations')->orderBy('id')->get()->toJson() || DB::table('audit_events')->count() !== $auditCount) {
    throw new RuntimeException('La repetición alteró el lote.');
}
$result = ['verified' => true, 'database' => DB::connection()->getDatabaseName(), 'demo_requests' => DB::table('service_requests')->where('demo_batch', 'arat-2026-v1')->count(),
    'total_requests' => DB::table('service_requests')->count(), 'idempotent' => true];
file_put_contents($directory.'/demo-rehearsal.json', json_encode($result, JSON_PRETTY_PRINT));
echo json_encode($result).PHP_EOL;

<?php

use App\Models\Quotation;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$directory = storage_path('app/private/final-maintenance');
$trial = json_decode(file_get_contents($directory.'/demo-rehearsal.json'), true, flags: JSON_THROW_ON_ERROR);
if (! $trial['verified'] || ! $trial['idempotent'] || $trial['demo_requests'] !== 15
    || DB::getDriverName() !== 'mysql' || DB::connection()->getDatabaseName() !== 'arat_studios' || ! $app->isDownForMaintenance()) {
    throw new RuntimeException('Falta ensayo verificado o mantenimiento de la base local esperada.');
}
$team = DB::table('users')->whereIn('id', [1, 2, 8, 9])->orderBy('id')->get()->toJson();
$preserved = [];
foreach (['projects', 'project_images', 'project_cover_histories', 'categories', 'services', 'tariff_groups', 'tariff_params', 'audit_events'] as $table) {
    $preserved[$table] = DB::table($table)->orderBy('id')->get()->all();
}
$unknown = DB::table('service_requests')->where('id', 9)->first();
$quote = DB::table('quotations')->where('service_request_id', 9)->get()->toJson();
$process = new Process([PHP_BINARY, __DIR__.'/cleanup.php', '--apply'], base_path());
$process->setTimeout(180)->mustRun();
echo $process->getOutput();
if (Artisan::call('migrate', ['--force' => true]) !== 0) {
    throw new RuntimeException(Artisan::output());
}
if (Artisan::call('demo:prepare', ['--batch' => 'arat-2026-v1', '--seed' => '20261124', '--date' => '2026-11-24', '--actor' => 1, '--apply' => true]) !== 0) {
    throw new RuntimeException(Artisan::output());
}
if ($team !== DB::table('users')->whereIn('id', [1, 2, 8, 9])->orderBy('id')->get()->toJson()) {
    throw new RuntimeException('Una cuenta del equipo cambió.');
}
foreach ($preserved as $table => $rows) {
    if ($rows != DB::table($table)->whereIn('id', array_map(fn ($row) => $row->id, $rows))->orderBy('id')->get()->all()) {
        throw new RuntimeException('Se alteró un registro conservado de '.$table.'.');
    }
}
$current = (array) DB::table('service_requests')->where('id', 9)->first();
foreach ((array) $unknown as $key => $value) {
    if ($current[$key] !== $value) {
        throw new RuntimeException('La solicitud de origen dudoso cambió.');
    }
}
if ($quote !== DB::table('quotations')->where('service_request_id', 9)->get()->toJson()) {
    throw new RuntimeException('La cotización conservada cambió.');
}
$pdfs = 0;
foreach (Quotation::whereNotNull('pdf_path')->get() as $quotation) {
    $content = Storage::disk('commercial')->get($quotation->pdf_path);
    if (! str_starts_with($content, '%PDF-') || ($quotation->pdf_hash && hash('sha256', $content) !== $quotation->pdf_hash)) {
        throw new RuntimeException('PDF ausente o alterado.');
    }
    $pdfs++;
}
$receipt = json_decode(file_get_contents($directory.'/backup-receipt.json'), true, flags: JSON_THROW_ON_ERROR);
$verifiedFiles = 0;
foreach ($receipt['files'] as $file => $hash) {
    if (str_starts_with($file, 'public/img/') || str_starts_with($file, 'public/media/')) {
        if (hash_file('sha256', base_path($file)) !== $hash) {
            throw new RuntimeException('Se alteró un recurso visual aprobado.');
        }
        $verifiedFiles++;
    }
}
$result = ['verified' => true, 'database' => 'arat_studios', 'team_ids_preserved' => [1, 2, 8, 9], 'team_count' => DB::table('users')->count(),
    'requests_before' => 9, 'requests_after' => DB::table('service_requests')->count(), 'demo_requests' => DB::table('service_requests')->where('demo_batch', 'arat-2026-v1')->count(),
    'uncertain_request_preserved' => true, 'catalogs_and_previous_audit_unchanged' => true, 'pdfs_verified' => $pdfs, 'visual_files_unchanged' => $verifiedFiles,
    'counts' => []];
foreach (DB::select('SHOW TABLES') as $row) {
    $table = array_values((array) $row)[0];
    $result['counts'][$table] = DB::table($table)->count();
}
file_put_contents($directory.'/final-result.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
Artisan::call('up');
echo json_encode($result, JSON_UNESCAPED_UNICODE).PHP_EOL;

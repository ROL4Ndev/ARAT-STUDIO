<?php

use App\Models\Deliverable;
use App\Models\DeliverableVersion;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local') || DB::getDriverName() !== 'mysql' || DB::connection()->getDatabaseName() !== 'arat_studios') {
    throw new RuntimeException('Solo se verifica la instalación habitual, sin escrituras de negocio.');
}
$root = storage_path('app/private/third-presentation');
$pointer = json_decode(file_get_contents($root.'/latest.json'), true, flags: JSON_THROW_ON_ERROR);
if (! preg_match('/^\d{8}_\d{6}$/', $pointer['folder'])) throw new RuntimeException('Referencia de respaldo inválida.');
$folder = $root.'/'.$pointer['folder'];
$backup = json_decode(file_get_contents($folder.'/receipt.json'), true, flags: JSON_THROW_ON_ERROR);
if (! $backup['restore_verified'] || hash_file('sha256', $folder.'/database.sql') !== $backup['sql_sha256']
    || hash_file('sha256', $folder.'/files.zip') !== $backup['zip_sha256']) throw new RuntimeException('El respaldo no conserva su integridad.');
$preserved = 0;
foreach ($backup['rows'] as $table => $rows) {
    if (in_array($table, ['sessions', 'cache', 'cache_locks'], true)) continue;
    foreach ($rows as $row) {
        if ($row['id'] === null) continue;
        $current = DB::table($table)->where('id', $row['id'])->first();
        if (! $current || hash('sha256', serialize((array) $current)) !== $row['sha256']) throw new RuntimeException('Cambió un registro anterior de '.$table.'.');
        $preserved++;
    }
}
foreach ($backup['files'] as $file) {
    if (! is_file($file['source']) || hash_file('sha256', $file['source']) !== $file['sha256']) throw new RuntimeException('Cambió un archivo anterior.');
}
$versions = [];
foreach (DeliverableVersion::with('deliverable')->get() as $version) {
    $contents = Storage::disk('commercial')->get($version->path);
    if (! str_starts_with($contents, '%PDF-') || ! str_contains(substr($contents, -1024), '%%EOF')
        || hash('sha256', $contents) !== $version->sha256) throw new RuntimeException('Archivo de demostración inválido.');
    $versions[] = ['deliverable' => $version->deliverable->code, 'number' => $version->number, 'bytes' => strlen($contents), 'sha256' => $version->sha256];
    if (($argv[1] ?? '') === '--export-demo') {
        $cases = ['00000000-0000-4000-8000-000000000101' => 'pendiente', '00000000-0000-4000-8000-000000000102' => 'revision', '00000000-0000-4000-8000-000000000103' => 'conforme'];
        if ($case = $cases[$version->deliverable->creation_key] ?? null) {
            $exports = base_path('docs/tercera-presentacion/archivos');
            if (! is_dir($exports)) mkdir($exports, 0755, true);
            file_put_contents($exports.'/escenario-'.$case.'-v'.$version->number.'.pdf', $contents);
        }
    }
}
$snapshot = ['deliverables' => DB::table('deliverables')->orderBy('id')->get()->toJson(),
    'versions' => DB::table('deliverable_versions')->orderBy('id')->get()->toJson(),
    'audit_count' => DB::table('audit_events')->count(), 'files' => Storage::disk('commercial')->allFiles()];
$snapshotFile = $root.'/demo-first-run.json';
$idempotent = null;
if (($argv[1] ?? '') === '--snapshot') {
    if (is_file($snapshotFile)) throw new RuntimeException('La referencia de primera ejecución ya existe.');
    file_put_contents($snapshotFile, json_encode($snapshot));
} elseif (is_file($snapshotFile)) {
    $idempotent = json_decode(file_get_contents($snapshotFile), true, flags: JSON_THROW_ON_ERROR) === $snapshot;
    if (! $idempotent) throw new RuntimeException('La repetición cambió los escenarios de demostración.');
}
$orphans = 0;
foreach (DB::select('SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL') as $fk) {
    $orphans += DB::table($fk->TABLE_NAME.' as child')->leftJoin($fk->REFERENCED_TABLE_NAME.' as parent', 'child.'.$fk->COLUMN_NAME, '=', 'parent.'.$fk->REFERENCED_COLUMN_NAME)
        ->whereNotNull('child.'.$fk->COLUMN_NAME)->whereNull('parent.'.$fk->REFERENCED_COLUMN_NAME)->count();
}
if ($orphans) throw new RuntimeException('Existen referencias huérfanas.');
$report = ['verified' => true, 'original_rows_unchanged' => $preserved, 'original_files_unchanged' => count($backup['files']),
    'team_accounts' => DB::table('users')->count(), 'faker_requests' => DB::table('service_requests')->where('demo_batch', 'arat-2026-v1')->count(),
    'requests' => DB::table('service_requests')->count(), 'projects' => DB::table('projects')->count(), 'gallery_images' => DB::table('project_images')->count(),
    'backup_directory' => 'storage/app/private/third-presentation/'.$pointer['folder'], 'restore_verified' => true,
    'restore_database' => $backup['restore_database'], 'demo_repeat_unchanged' => $idempotent, 'foreign_key_orphans' => 0,
    'deliverables' => Deliverable::with(['quotation.serviceRequest', 'latestVersion'])->get()->map(fn ($d) => ['code' => $d->code,
        'title' => $d->latestVersion->title, 'status' => $d->status, 'latest_version' => $d->latestVersion->number,
        'request' => $d->quotation->serviceRequest->code, 'quotation' => $d->quotation->code])->all(), 'version_files' => $versions];
if (! is_dir(base_path('docs/tercera-presentacion'))) mkdir(base_path('docs/tercera-presentacion'), 0755, true);
file_put_contents(base_path('docs/tercera-presentacion/data-verification.json'), json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode(['verified' => true, 'preserved_rows' => $preserved, 'preserved_files' => count($backup['files']), 'deliverables' => count($report['deliverables']), 'versions' => count($versions), 'idempotent' => $idempotent]).PHP_EOL;

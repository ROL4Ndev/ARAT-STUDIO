<?php

use App\Models\Quotation;
use App\Services\PortfolioInventory;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$output = getopt('', ['output:'])['output'] ?? 'data-verification.json';
if (! preg_match('/\A[a-z0-9][a-z0-9-]*\.json\z/', $output)) {
    throw new RuntimeException('El informe debe ser un nombre JSON dentro de docs/final.');
}
if (DB::getDriverName() !== 'mysql' || DB::connection()->getDatabaseName() !== 'arat_studios') {
    throw new RuntimeException('Conexión inesperada.');
}
$private = storage_path('app/private/final-maintenance');
$initial = json_decode(file_get_contents($private.'/inventory-private.json'), true, flags: JSON_THROW_ON_ERROR);
$backup = json_decode(file_get_contents($private.'/backup-receipt.json'), true, flags: JSON_THROW_ON_ERROR);
$counts = [];
foreach (DB::select('SHOW TABLES') as $row) {
    $table = array_values((array) $row)[0];
    $counts[$table] = DB::table($table)->count();
}
foreach ($initial['users'] as $user) {
    if (in_array($user['id'], [1, 2, 8, 9], true) && $user != (array) DB::table('users')->where('id', $user['id'])->first()) {
        throw new RuntimeException('Cambió una cuenta conservada.');
    }
}
foreach (['projects', 'project_images', 'project_cover_histories', 'categories', 'services', 'tariff_groups', 'tariff_params'] as $table) {
    $hash = hash('sha256', serialize(DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all()));
    if ($hash !== $backup['tables'][$table]['sha256']) {
        throw new RuntimeException('Cambió un catálogo conservado.');
    }
}
foreach (DB::select('SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL') as $fk) {
    if (DB::table($fk->TABLE_NAME.' as child')->leftJoin($fk->REFERENCED_TABLE_NAME.' as parent', 'child.'.$fk->COLUMN_NAME, '=', 'parent.'.$fk->REFERENCED_COLUMN_NAME)
        ->whereNotNull('child.'.$fk->COLUMN_NAME)->whereNull('parent.'.$fk->REFERENCED_COLUMN_NAME)->exists()) {
        throw new RuntimeException('Referencia huérfana.');
    }
}
$pdfs = 0;
foreach (Quotation::whereNotNull('pdf_path')->get() as $quotation) {
    $content = Storage::disk('commercial')->get($quotation->pdf_path);
    if (! str_starts_with($content, '%PDF-') || ($quotation->pdf_hash && hash('sha256', $content) !== $quotation->pdf_hash)) {
        throw new RuntimeException('PDF ausente o alterado.');
    }
    $pdfs++;
}
$visualFiles = [];
$duplicates = [];
foreach ($backup['files'] as $file => $hash) {
    if ((str_starts_with($file, 'public/img/') || str_starts_with($file, 'public/media/')) && ! str_ends_with($file, '/.gitkeep')) {
        if (! is_file(base_path($file)) || hash_file('sha256', base_path($file)) !== $hash) {
            throw new RuntimeException('Se alteró un recurso aprobado.');
        }
        $visualFiles[$file] = ['bytes' => filesize(base_path($file)), 'sha256' => $hash];
        $duplicates[$hash][] = $file;
    }
}
$inventory = app(PortfolioInventory::class)->inspect();
$referencedImages = array_merge(DB::table('projects')->pluck('cover_image')->all(), DB::table('project_images')->pluck('path')->all());
$unreferencedOriginals = array_values(array_filter(array_keys($visualFiles), fn ($file) => str_starts_with($file, 'public/img/')
    && substr_count($file, '/') > 2 && ! in_array(substr($file, strlen('public/')), $referencedImages, true)));
$incomplete = DB::table('projects')->get()->filter(function ($project) {
    $path = str_starts_with($project->cover_image, 'img/') ? public_path($project->cover_image) : Storage::disk('public')->path($project->cover_image);

    return $project->is_active && (mb_strlen(trim($project->title)) < 3 || mb_strlen(trim($project->description)) < 20 || ! is_file($path));
})->count();
if ($incomplete) {
    throw new RuntimeException('Hay fichas publicadas incompletas.');
}
$rehearsal = json_decode(file_get_contents($private.'/demo-rehearsal.json'), true, flags: JSON_THROW_ON_ERROR);
$result = ['database' => 'arat_studios', 'verified' => true, 'team_preserved' => 4, 'counts_before' => $initial['counts'], 'counts_after' => $counts,
    'demo_requests' => DB::table('service_requests')->where('demo_batch', 'arat-2026-v1')->count(),
    'retained_uncertain_requests' => DB::table('service_requests')->where('id', 9)->count(),
    'pdfs_verified' => $pdfs, 'visual_files_unchanged' => count($visualFiles),
    'incomplete_published_projects' => 0, 'foreign_key_orphans' => 0, 'demo_idempotence_verified_in_restore' => $rehearsal['idempotent'],
    'portfolio_inventory' => $inventory, 'visual_files' => $visualFiles, 'duplicate_visual_files' => array_values(array_filter($duplicates, fn ($paths) => count($paths) > 1)),
    'original_project_files_without_database_reference' => $unreferencedOriginals];
if (! is_dir(base_path('docs/final'))) {
    mkdir(base_path('docs/final'), 0755, true);
}
file_put_contents(base_path('docs/final/'.$output), json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo json_encode(array_diff_key($result, array_flip(['counts_before', 'counts_after', 'portfolio_inventory', 'visual_files', 'duplicate_visual_files']))).PHP_EOL;

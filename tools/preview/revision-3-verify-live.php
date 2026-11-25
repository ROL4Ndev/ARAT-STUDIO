<?php

use App\Models\Project;
use App\Models\ProjectImage;
use App\Models\Quotation;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (DB::getDriverName() !== 'mysql' || DB::connection()->getDatabaseName() !== 'arat_studios') {
    throw new RuntimeException('Conexión inesperada.');
}
$before = json_decode(file_get_contents(storage_path('app/revision-3/database-before.json')), true);
$keys = ['title', 'slug', 'description', 'cover_image', 'created_at', 'category_id', 'is_active', 'is_featured', 'featured_order', 'status_reason', 'deactivated_at', 'deactivated_by'];
foreach ($before['projects'] as $old) {
    $current = (array) DB::table('projects')->where('id', $old['id'])->first();
    foreach ($keys as $key) {
        if ($old[$key] !== $current[$key]) {
            throw new RuntimeException('Se detectó un cambio en contenido editorial previo.');
        }
    }
}
$commercialTables = ['service_requests', 'quotations', 'request_revisions', 'technical_evaluations', 'request_files', 'service_request_histories', 'client_access_tokens', 'client_grants', 'notification_records', 'notification_deliveries', 'work_intents', 'tariff_groups', 'tariff_params'];
foreach ($commercialTables as $table) {
    if (isset($before['table_counts'][$table]) && DB::table($table)->count() !== $before['table_counts'][$table]) {
        throw new RuntimeException('Cambió el recuento comercial durante la revisión.');
    }
}
$pdfs = 0;
foreach (Quotation::whereNotNull('pdf_path')->whereNotNull('pdf_hash')->get(['pdf_path', 'pdf_hash']) as $quotation) {
    $path = Storage::disk('commercial')->path($quotation->pdf_path);
    if (! is_file($path) || hash_file('sha256', $path) !== $quotation->pdf_hash) {
        throw new RuntimeException('Un PDF histórico no coincide con su huella almacenada.');
    }
    $pdfs++;
}
$priorImages = ProjectImage::count();
$priorProjects = Project::count();
$priorVersions = Project::orderBy('id')->pluck('lock_version', 'id')->all();
$fallback = DB::table('categories')->where('name', 'Arquitectura institucional')->value('id');
$actor = DB::table('users')->where('role', 'admin')->where('is_active', true)->orderBy('id')->value('id');
if (Artisan::call('portfolio:incorporar', ['--apply' => true, '--category' => $fallback, '--actor' => $actor, '--include-galleries' => true]) !== 0) {
    throw new RuntimeException('No se pudo comprobar la repetición del importador.');
}
if (Project::count() !== $priorProjects || ProjectImage::count() !== $priorImages || Project::orderBy('id')->pluck('lock_version', 'id')->all() !== $priorVersions) {
    throw new RuntimeException('La repetición no fue idempotente.');
}
$duplicates = DB::query()->fromSub(DB::table('project_images')->select('project_id', 'sha256')->groupBy('project_id', 'sha256')->havingRaw('COUNT(*) > 1'), 'duplicates')->count();
if ($duplicates !== 0) {
    throw new RuntimeException('Hay entradas duplicadas de galería.');
}
$report = [
    'existing_editorial_records_verified' => count($before['projects']), 'commercial_table_counts_unchanged' => true,
    'historical_pdfs_verified_against_stored_sha256' => $pdfs,
    'import_repeated_without_new_projects_images_or_versions' => true, 'duplicate_gallery_entries' => $duplicates,
    'projects_total' => Project::count(), 'gallery_entries' => ProjectImage::count(),
    'published' => Project::where('is_active', true)->whereHas('projectCategory', fn ($query) => $query->where('is_active', true))->count(),
    'featured_published' => Project::where('is_featured', true)->where('is_active', true)->whereHas('projectCategory', fn ($query) => $query->where('is_active', true))->count(),
];
file_put_contents(base_path('docs/revision-3/live-integrity.json'), json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;

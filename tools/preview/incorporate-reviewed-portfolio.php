<?php

use App\Models\AuditEvent;
use App\Models\Category;
use App\Models\Project;
use App\Models\ProjectImage;
use App\Models\User;
use App\Services\CatalogWorkflowService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$demo = in_array('--demo', $argv, true);
$apply = in_array('--apply', $argv, true);
$publish = in_array('--publish-reviewed', $argv, true);
if ($demo) {
    $app = require __DIR__.'/bootstrap.php';
} else {
    require dirname(__DIR__, 2).'/vendor/autoload.php';
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
}
$app->make(Kernel::class)->bootstrap();
if ($demo ? DB::getDriverName() !== 'sqlite' : (DB::getDriverName() !== 'mysql' || DB::connection()->getDatabaseName() !== 'arat_studios')) {
    throw new RuntimeException('La conexión no corresponde al modo solicitado.');
}
$editorial = require resource_path('data/portfolio-editorial.php');
if (! $apply) {
    echo 'Solo revisión. Para incorporar: --apply; para publicar únicamente las propuestas completas revisadas: --publish-reviewed.'.PHP_EOL;
    foreach ($editorial as $folder => $item) {
        echo $folder.' → '.$item['category'].PHP_EOL;
    }
    exit;
}
if (! $demo) {
    $receipt = json_decode(file_get_contents(storage_path('app/revision-3/backup-receipt.json')), true);
    $backup = storage_path('app/revision-3/'.$receipt['file']);
    if (! is_file($backup) || hash_file('sha256', $backup) !== $receipt['sha256']) {
        throw new RuntimeException('Primero se requiere un respaldo verificado.');
    }
}
function businessFingerprint(): array
{
    $hashes = [];
    foreach (['service_requests', 'quotations', 'request_revisions', 'technical_evaluations', 'request_files', 'service_request_histories', 'client_access_tokens', 'client_grants', 'notification_records', 'notification_deliveries', 'work_intents', 'tariff_groups', 'tariff_params'] as $table) {
        if (Schema::hasTable($table)) {
            $hashes[$table] = hash('sha256', DB::table($table)->orderBy('id')->get()->toJson());
        }
    }

    return $hashes;
}
$beforeBusiness = businessFingerprint();
$beforeIds = Project::pluck('id')->all();
$beforeEditorial = Project::get()->mapWithKeys(fn ($project) => [$project->id => $project->only(['title', 'slug', 'description', 'cover_image', 'category_id', 'is_active', 'is_featured', 'featured_order'])])->all();
$actor = User::where('role', 'admin')->where('is_active', true)->orderBy('id')->firstOrFail();
$catalog = app(CatalogWorkflowService::class);
$categoryNames = array_unique(array_column($editorial, 'category'));
foreach ($categoryNames as $name) {
    if (! Category::where('name', $name)->exists()) {
        $catalog->save('categories', null, [
            'name' => $name, 'description' => 'Clasificación por la tipología visible en las láminas y representaciones del portafolio.',
            'reason' => 'Clasificar propuestas verificadas en los recursos existentes; sin atribuir obras ejecutadas.',
        ], null, $actor);
    }
}
$fallback = Category::where('name', 'Arquitectura institucional')->firstOrFail();
$result = Artisan::call('portfolio:incorporar', [
    '--apply' => true, '--category' => $fallback->id, '--actor' => $actor->id, '--include-galleries' => true,
]);
if ($result !== 0) {
    throw new RuntimeException('El importador no confirmó la incorporación.');
}
$prepared = [];
$pending = [];
foreach ($editorial as $folder => $item) {
    $project = Project::where('cover_image', 'like', 'img/'.$folder.'/%')->firstOrFail();
    // Los registros previos y cualquier edición editorial posterior quedan fuera de esta preparación.
    if (in_array($project->id, $beforeIds, true)) {
        continue;
    }
    $imported = AuditEvent::where('resource_type', $project->getMorphClass())->where('resource_id', $project->id)->where('action', 'portfolio_draft_imported')->exists();
    if (! $imported || $project->description !== '') {
        continue;
    }
    foreach ($item['evidence'] as $file) {
        if (! is_file(public_path('img/'.$folder.'/'.$file))) {
            throw new RuntimeException('Falta una referencia visual de la revisión editorial.');
        }
    }
    $category = Category::where('name', $item['category'])->firstOrFail();
    $project = $catalog->save('projects', $project, [
        'title' => $item['title'], 'description' => $item['description'], 'category_id' => $category->id,
        'is_active' => false, 'lock_version' => $project->lock_version,
        'reason' => 'Preparación editorial verificada en '.implode(', ', $item['evidence']).'; propuesta, sin afirmar ejecución ni datos no documentados.',
    ], null, $actor);
    if ($publish && $category->is_active && $project->images()->where('is_active', true)->exists()) {
        $catalog->status($project, true, $project->lock_version, 'Publicar propuesta completa tras revisar título, tipología, descripción, portada y galería.', $actor);
    } else {
        $pending[] = $project->title;
    }
    $prepared[] = $project->title;
}
foreach ($beforeEditorial as $id => $fields) {
    if (Project::findOrFail($id)->only(array_keys($fields)) !== $fields) {
        throw new RuntimeException('Cambió contenido editorial previo; revisar antes de continuar.');
    }
}
if (businessFingerprint() !== $beforeBusiness) {
    throw new RuntimeException('Cambió una tabla comercial durante la incorporación; revisar la concurrencia externa.');
}
$report = [
    'environment' => $demo ? 'SQLite aislado' : 'MariaDB habitual, con respaldo previo',
    'previous_projects' => count($beforeIds), 'incorporated_projects' => Project::whereNotIn('id', $beforeIds)->count(),
    'prepared_titles' => $prepared, 'projects_total' => Project::count(),
    'published' => Project::where('is_active', true)->whereHas('projectCategory', fn ($query) => $query->where('is_active', true))->count(),
    'drafts_or_hidden' => Project::where(fn ($query) => $query->where('is_active', false)->orWhereHas('projectCategory', fn ($category) => $category->where('is_active', false)))->count(),
    'featured_published' => Project::where('is_featured', true)->where('is_active', true)->whereHas('projectCategory', fn ($query) => $query->where('is_active', true))->count(),
    'gallery_entries' => ProjectImage::count(), 'pending_titles' => $pending,
    'existing_editorial_content_preserved' => true, 'commercial_table_fingerprints_unchanged' => true,
];
$directory = base_path('docs/revision-3');
if (! is_dir($directory)) {
    mkdir($directory, 0755, true);
}
file_put_contents($directory.'/portfolio-'.($demo ? 'demo' : 'live').'.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;

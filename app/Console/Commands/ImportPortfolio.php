<?php

namespace App\Console\Commands;

use App\Models\AuditEvent;
use App\Models\Category;
use App\Models\Project;
use App\Models\User;
use App\Services\AuditService;
use App\Services\PortfolioInventory;
use App\Services\ProjectGalleryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportPortfolio extends Command
{
    protected $signature = 'portfolio:incorporar {--apply : Crear borradores faltantes} {--category= : Categoría confirmada para los nuevos borradores} {--actor= : Administrador que autoriza} {--include-galleries : Vincular imágenes también en coincidencias únicas existentes}';

    protected $description = 'Revisar o incorporar proyectos faltantes como borradores, sin sobrescribir contenido existente.';

    public function handle(PortfolioInventory $inventory, ProjectGalleryService $gallery, AuditService $audit): int
    {
        $report = $inventory->inspect();
        if ($report['database_status'] !== 'consultada_sin_escrituras') {
            $this->error('Primero resuelve el acceso a la base y genera el inventario. No se incorporó contenido.');

            return self::FAILURE;
        }
        if (! $this->option('apply')) {
            $this->table(['Carpeta', 'Resultado previsto'], array_map(fn ($group) => [$group['folder'], $group['resolution'] === 'proyecto_faltante' ? 'Crear borrador; faltan categoría y descripción confirmadas' : 'Conservar registro existente'], $report['groups']));
            $this->info('Solo revisión. Para incorporar, indica --apply, --category y --actor. No se publica ni se destaca automáticamente.');

            return self::SUCCESS;
        }
        $actor = User::whereKey($this->option('actor'))->where('role', 'admin')->where('is_active', true)->first();
        $category = Category::find($this->option('category'));
        if (! $actor || ! $category) {
            $this->error('Se requieren un administrador activo y una categoría existente confirmada.');

            return self::FAILURE;
        }
        $created = 0;
        $preserved = 0;
        foreach ($report['groups'] as $group) {
            if ($group['resolution'] === 'coincidencia_ambigua' || $group['images'] === []) {
                $this->warn('Revisión manual necesaria: '.$group['folder']);

                continue;
            }
            $project = DB::transaction(function () use ($group, $actor, $category, $audit, &$created, &$preserved) {
                $existing = Project::where(fn ($query) => $query->where('title', $group['proposed_title'])
                    ->orWhere('slug', $group['proposed_slug'])
                    ->orWhere('cover_image', 'like', 'img/'.$group['folder'].'/%'))->lockForUpdate()->get();
                if (count($group['matches']) === 1) {
                    $existing = Project::whereKey($group['matches'][0]['id'])->lockForUpdate()->get();
                }
                if ($existing->isNotEmpty()) {
                    $preserved++;

                    return $existing->count() === 1 ? $existing->first() : null;
                }
                $project = Project::create([
                    'title' => $group['proposed_title'], 'slug' => $group['proposed_slug'],
                    'category_id' => $category->id, 'description' => '',
                    'cover_image' => $group['images'][0]['path'],
                    'is_active' => false, 'is_featured' => false,
                ]);
                $audit->record($project, 'portfolio_draft_imported', 'Borrador desde carpeta existente; pendiente de completar y revisar antes de publicar.', [], $project->toArray(), $actor);
                $created++;

                return $project;
            }, 3);
            $pendingImportedDraft = $project && ! $project->is_active && AuditEvent::where('resource_type', $project->getMorphClass())
                ->where('resource_id', $project->id)->where('action', 'portfolio_draft_imported')->exists();
            if ($project && ($group['resolution'] === 'proyecto_faltante' || $pendingImportedDraft || $this->option('include-galleries'))) {
                $gallery->add($project, $actor, $project->lock_version, array_column($group['images'], 'path'), [], 'Vincular originales identificados en el inventario, sin copiarlos.');
            }
        }
        $this->info("Borradores creados: {$created}. Registros conservados: {$preserved}. Ningún proyecto fue publicado o destacado.");

        return self::SUCCESS;
    }
}

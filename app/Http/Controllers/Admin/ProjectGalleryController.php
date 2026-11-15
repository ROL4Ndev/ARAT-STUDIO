<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GalleryRequest;
use App\Models\Project;
use App\Models\ProjectImage;
use App\Services\CatalogWorkflowService;
use App\Services\PortfolioInventory;
use App\Services\ProjectGalleryService;

class ProjectGalleryController extends Controller
{
    public function add(GalleryRequest $request, Project $project, ProjectGalleryService $gallery, PortfolioInventory $inventory)
    {
        $sources = $request->filled('source_folder') ? array_column($inventory->folders()[$request->validated('source_folder')], 'path') : [];
        $gallery->add($project, $request->user(), (int) $request->validated('lock_version'), $sources, $request->file('images', []), $request->validated('reason'));

        return $this->back($project, 'Imágenes vinculadas. Los originales y las imágenes repetidas se conservaron sin duplicar entradas.');
    }

    public function order(GalleryRequest $request, Project $project, ProjectGalleryService $gallery)
    {
        $gallery->reorder($project, $request->user(), (int) $request->validated('lock_version'), $request->validated('positions'), $request->validated('reason'));

        return $this->back($project, 'Orden de la galería actualizado.');
    }

    public function status(GalleryRequest $request, Project $project, ProjectImage $image, ProjectGalleryService $gallery)
    {
        abort_unless($image->project_id === $project->id, 404);
        $gallery->changeStatus($project, $image, $request->user(), (int) $request->validated('lock_version'), $request->boolean('is_active'), $request->validated('reason'));

        return $this->back($project, 'Estado de la imagen actualizado; el original se conserva.');
    }

    public function cover(GalleryRequest $request, Project $project, ProjectImage $image, CatalogWorkflowService $catalog)
    {
        abort_unless($image->project_id === $project->id, 404);
        $data = $project->only(['title', 'description', 'category_id', 'is_active']) + $request->safe()->only(['reason', 'lock_version']);
        $catalog->save('projects', $project, $data, null, $request->user(), galleryCover: $image);

        return $this->back($project, 'Portada actualizada. Su versión anterior permanece en el historial.');
    }

    private function back(Project $project, string $status)
    {
        return redirect()->to(route('admin.projects.show', $project->id).'#galeria')->with('success', $status);
    }
}

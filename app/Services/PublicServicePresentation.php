<?php

namespace App\Services;

use App\Models\OfferedService;
use App\Models\Project;
use Illuminate\Support\Collection;

class PublicServicePresentation
{
    public function groups(): Collection
    {
        $visuals = collect(config('studio.service_visuals', []));
        $slugs = $visuals->flatMap(fn ($visual) => array_filter([$visual['project_slug'] ?? null, $visual['illustration_slug'] ?? null]))->unique();
        // Una consulta para todas las referencias; una selección editorial no sustituye la publicación.
        $projects = Project::published()->whereIn('slug', $slugs)
            ->get(['id', 'title', 'slug'])->keyBy('slug');

        return OfferedService::where('is_active', true)->orderBy('display_order')->orderBy('id')
            ->get(['id', 'code', 'name', 'description', 'audience'])
            ->map(function ($service) use ($visuals, $projects) {
                $visual = $visuals->get($service->code);
                $project = $projects->get($visual['project_slug'] ?? $visual['illustration_slug'] ?? '');

                return [
                    'id' => $service->id, 'name' => $service->name, 'audience' => $service->audience,
                    'description' => $visual['description'] ?? $service->description,
                    'image' => $project ? ($visual['image'] ?? null) : null,
                    'image_alt' => $visual['alt'] ?? '',
                    'caption' => $visual['caption'] ?? $project?->title,
                    'project' => isset($visual['project_slug']) ? $project : null,
                ];
            })
            ->groupBy(fn ($service) => $service['audience'] === 'estudiantes' ? 'Servicios para estudiantes' : 'Servicios generales');
    }
}

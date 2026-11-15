<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class PortfolioInventory
{
    public function folders(): array
    {
        $folders = [];
        foreach (File::directories(public_path('img')) as $directory) {
            $files = File::files($directory);
            usort($files, fn ($left, $right) => strnatcasecmp($left->getFilename(), $right->getFilename()));
            $images = [];
            foreach ($files as $file) {
                $size = @getimagesize($file->getPathname());
                if (! $size || ! in_array($size[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
                    continue;
                }
                $images[] = [
                    'path' => 'img/'.basename($directory).'/'.$file->getFilename(),
                    'width' => $size[0], 'height' => $size[1],
                    'bytes' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getPathname()),
                ];
            }
            $folders[basename($directory)] = $images;
        }
        ksort($folders, SORT_NATURAL | SORT_FLAG_CASE);

        return $folders;
    }

    public function inspect(): array
    {
        $folders = $this->folders();
        try {
            $projects = Project::get(['id', 'title', 'slug', 'cover_image', 'category_id', 'description', 'is_active']);
            $databaseStatus = 'consultada_sin_escrituras';
        } catch (\Throwable) {
            $projects = collect();
            $databaseStatus = 'no_disponible_no_se_pueden_confirmar_coincidencias';
        }
        $groups = [];
        foreach ($folders as $folder => $images) {
            $slug = Str::slug($folder);
            $matches = $projects->filter(fn ($project) => Str::slug($project->title) === $slug
                || $project->slug === $slug
                || str_starts_with($project->slug, $slug.'-')
                || str_starts_with($project->cover_image, 'img/'.$folder.'/'));
            $groups[] = [
                'folder' => $folder, 'proposed_title' => $folder, 'proposed_slug' => $slug,
                'images' => $images, 'matches' => $matches->values()->map->only(['id', 'title', 'slug', 'is_active'])->all(),
                'resolution' => $databaseStatus !== 'consultada_sin_escrituras' ? 'requiere_consultar_base' : match ($matches->count()) {
                    0 => 'proyecto_faltante', 1 => 'coincidencia_unica', default => 'coincidencia_ambigua'
                },
                'missing_data' => $matches->isEmpty() ? ['categoría confirmada', 'descripción editorial', 'confirmación del título y de las imágenes antes de publicar'] : [],
            ];
        }

        return ['generated_at' => now('America/Lima')->toIso8601String(), 'database_status' => $databaseStatus, 'folder_count' => count($groups), 'image_count' => array_sum(array_map('count', $folders)), 'groups' => $groups];
    }
}

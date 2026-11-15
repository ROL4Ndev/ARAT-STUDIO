<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectImage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class ProjectGalleryService
{
    public function __construct(private AuditService $audit) {}

    public function add(Project $record, User $actor, int $version, array $sources, array $uploads, string $reason): void
    {
        Gate::forUser($actor)->authorize('update', $record);
        $prepared = [];
        foreach ($sources as $source) {
            $absolute = realpath(public_path($source));
            $root = realpath(public_path('img'));
            if (! $absolute || ! $root || ! str_starts_with($source, 'img/') || str_contains($source, '..') || ! str_starts_with(strtolower($absolute), strtolower($root.DIRECTORY_SEPARATOR))) {
                throw ValidationException::withMessages(['source_folder' => 'La imagen debe pertenecer a public/img.']);
            }
            $prepared[] = $this->prepare($absolute, $source);
        }
        foreach ($uploads as $upload) {
            $hash = hash_file('sha256', $upload->getRealPath());
            $extension = image_type_to_extension(getimagesize($upload->getRealPath())[2], false);
            $path = 'project-gallery/'.$hash.'.'.$extension;
            if (! Storage::disk('public')->exists($path)) {
                Storage::disk('public')->put($path, file_get_contents($upload->getRealPath()));
            }
            $prepared[] = $this->prepare(Storage::disk('public')->path($path), $path);
        }
        $this->generateThumbnails($prepared);
        DB::transaction(function () use ($record, $actor, $version, $prepared, $reason) {
            $project = DomainLock::get($record, $version);
            $existing = $project->images()->get()->keyBy('sha256');
            $position = (int) $existing->max('sort_order');
            $new = [];
            foreach ($prepared as $image) {
                if ($existing->has($image['sha256']) || isset($new[$image['sha256']])) {
                    continue;
                }
                $new[$image['sha256']] = $image + ['project_id' => $project->id, 'sort_order' => ++$position, 'created_by' => $actor->id, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()];
            }
            if ($new === []) {
                return;
            }
            ProjectImage::insert(array_values($new));
            // La referencia de portada añade metadatos; no cambia la imagen ni el contenido editorial.
            $cover = $project->images()->where('path', $project->cover_image)->first();
            $project->update(['lock_version' => $project->lock_version + 1, 'cover_image_id' => $cover?->id ?? $project->cover_image_id]);
            $this->audit->record($project, 'gallery_added', $reason, [], ['added_paths' => array_column($new, 'path')], $actor);
        }, 3);
    }

    public function reorder(Project $record, User $actor, int $version, array $positions, string $reason): void
    {
        Gate::forUser($actor)->authorize('update', $record);
        DB::transaction(function () use ($record, $actor, $version, $positions, $reason) {
            $project = DomainLock::get($record, $version);
            $images = $project->images()->where('is_active', true)->get();
            $submitted = array_map('intval', array_keys($positions));
            $current = $images->pluck('id')->all();
            sort($submitted);
            sort($current);
            abort_unless($submitted === $current, 409, 'La galería cambió. Recarga antes de guardar el orden.');
            $before = $images->pluck('sort_order', 'id')->all();
            foreach ($images as $image) {
                $image->update(['sort_order' => (int) $positions[$image->id]]);
            }
            $project->increment('lock_version');
            $this->audit->record($project, 'gallery_reordered', $reason, ['positions' => $before], ['positions' => $positions], $actor);
        }, 3);
    }

    public function changeStatus(Project $record, ProjectImage $image, User $actor, int $version, bool $active, string $reason): void
    {
        Gate::forUser($actor)->authorize('update', $record);
        DB::transaction(function () use ($record, $image, $actor, $version, $active, $reason) {
            $project = DomainLock::get($record, $version);
            abort_unless($image->project_id === $project->id, 404);
            if (! $active && ($project->cover_image_id === $image->id || $project->cover_image === $image->path)) {
                throw ValidationException::withMessages(['image' => 'Elige otra portada antes de archivar esta imagen.']);
            }
            $image->update(['is_active' => $active]);
            $project->increment('lock_version');
            $this->audit->record($project, $active ? 'gallery_restored' : 'gallery_archived', $reason, [], ['image_id' => $image->id, 'is_active' => $active], $actor);
        }, 3);
    }

    private function prepare(string $absolute, string $path): array
    {
        $size = @getimagesize($absolute);
        if (! $size || ! in_array($size[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) || $size[0] * $size[1] > 40000000) {
            throw ValidationException::withMessages(['images' => 'Usa imágenes JPG, PNG o WebP de hasta 40 megapíxeles.']);
        }
        $hash = hash_file('sha256', $absolute);
        $thumbnail = 'project-thumbnails/'.$hash.'-480.webp';

        return ['path' => $path, 'thumbnail_path' => $thumbnail, 'sha256' => $hash, 'width' => $size[0], 'height' => $size[1]];
    }

    private function generateThumbnails(array $images): void
    {
        $disk = Storage::disk('public');
        $jobs = [];
        foreach ($images as $image) {
            if (! $disk->exists($image['thumbnail_path'])) {
                $jobs[$image['sha256']] = [
                    'source' => str_starts_with($image['path'], 'img/') ? public_path($image['path']) : $disk->path($image['path']),
                    'destination' => $disk->path($image['thumbnail_path']),
                ];
            }
        }
        if ($jobs === []) {
            return;
        }
        $disk->makeDirectory('project-thumbnails');
        $process = new Process([config('filesystems.thumbnail_node_binary', 'node'), base_path('tools/thumbnail.mjs')], base_path());
        $process->setInput(json_encode(array_values($jobs)))->setTimeout(120)->run();
        if (! $process->isSuccessful()) {
            throw ValidationException::withMessages(['images' => 'No se pudieron preparar las miniaturas. Comprueba Node y la instalación de Sharp antes de reintentar. Los originales se conservan.']);
        }
    }
}

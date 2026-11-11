<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProjectRequest;
use App\Http\Requests\Admin\UpdateProjectRequest;
use App\Models\Category;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class ProjectController extends Controller
{
    public function index(): View
    {
        $projects = Project::query()
            ->with('projectCategory')
            ->when(request()->filled('q'), function ($query): void {
                $query->where('title', 'like', '%'.request('q').'%');
            })
            ->orderBy('title')
            ->paginate(15)
            ->withQueryString();

        return view('admin.projects.index', compact('projects'));
    }

    public function create(): View
    {
        $categories = Category::query()->orderBy('name')->get();

        return view('admin.projects.create', compact('categories'));
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('cover_image');
        $data['slug'] = $this->uniqueSlug($data['title']);
        $data['is_active'] = $request->boolean('is_active');
        $path = $this->storeCover($request);

        try {
            DB::transaction(function () use ($data, $path): void {
                $project = new Project($data + ['cover_image' => $path]);

                if (! $project->save()) {
                    throw new RuntimeException('No se pudo guardar el proyecto.');
                }
            });
        } catch (Throwable $exception) {
            $this->removeNewCover($path);

            throw $exception;
        }

        return redirect()
            ->route('admin.projects.index')
            ->with('success', 'El proyecto se creó correctamente.');
    }

    public function show(Project $project): View
    {
        $project->load('projectCategory');

        return view('admin.projects.show', compact('project'));
    }

    public function edit(Project $project): View
    {
        $categories = Category::query()->orderBy('name')->get();

        return view('admin.projects.edit', compact('project', 'categories'));
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $data = $request->safe()->except('cover_image');
        $data['is_active'] = $request->boolean('is_active');
        $newPath = $request->hasFile('cover_image')
            ? $this->storeCover($request)
            : null;
        $oldPath = $project->cover_image;

        if ($newPath !== null) {
            $data['cover_image'] = $newPath;
        }

        try {
            DB::transaction(function () use ($project, $data): void {
                if (! $project->update($data)) {
                    throw new RuntimeException('No se pudo actualizar el proyecto.');
                }
            });
        } catch (Throwable $exception) {
            if ($newPath !== null) {
                $this->removeNewCover($newPath);
            }

            throw $exception;
        }

        if ($newPath !== null) {
            $this->removeReplacedCover($oldPath);
        }

        return redirect()
            ->route('admin.projects.index')
            ->with('success', 'El proyecto se actualizó correctamente.');
    }

    private function storeCover(StoreProjectRequest|UpdateProjectRequest $request): string
    {
        $path = $request->file('cover_image')->store('project-covers', 'public');

        if ($path === false) {
            throw new RuntimeException('No se pudo guardar la portada. Verifica los permisos de almacenamiento e inténtalo nuevamente.');
        }

        return $path;
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::limit(Str::slug($title) ?: 'proyecto', 180, '');
        $slug = $base;
        $suffix = 2;

        while (Project::query()->where('slug', $slug)->exists()) {
            $slug = Str::limit($base, 175, '').'-'.$suffix++;
        }

        return $slug;
    }

    private function removeNewCover(string $path): void
    {
        if (! Storage::disk('public')->delete($path)) {
            Log::error('No se pudo limpiar una portada después de fallar el guardado del proyecto.', [
                'path' => $path,
            ]);
        }
    }

    private function removeReplacedCover(string $path): void
    {
        if (! Str::startsWith($path, 'project-covers/') || str_contains($path, '..')) {
            return;
        }

        if (Storage::disk('public')->exists($path) && ! Storage::disk('public')->delete($path)) {
            Log::warning('No se pudo eliminar la portada reemplazada del almacenamiento público.', [
                'path' => $path,
            ]);
        }
    }
}

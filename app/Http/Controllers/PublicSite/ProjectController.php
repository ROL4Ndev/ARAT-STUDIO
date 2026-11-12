<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicSite\FilterProjectsRequest;
use App\Models\Category;
use App\Models\Project;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(FilterProjectsRequest $request): View
    {
        $filters = $request->validated();
        $selectedCategory = $filters['category'] ?? '';

        $categories = Category::query()
            ->where('is_active', true)
            ->whereHas('projects', function ($query) {
                $query->where('is_active', true);
            })
            ->orderBy('name')
            ->get();

        $projects = Project::query()
            ->with('projectCategory')
            ->where('is_active', true)
            ->whereHas('projectCategory', fn ($q) => $q->where('is_active', true))
            ->when($selectedCategory !== '', function ($query) use ($selectedCategory) {
                $query->whereHas('projectCategory', function ($categoryQuery) use ($selectedCategory) {
                    $categoryQuery->where('slug', $selectedCategory);
                });
            })
            ->orderBy('id')
            ->paginate(9)
            ->appends(['category' => $selectedCategory]);

        return view('projects.index', compact(
            'projects',
            'categories',
            'selectedCategory'
        ));
    }

    public function show(Project $project): View
    {
        abort_unless($project->is_active && $project->projectCategory?->is_active, 404);

        $project->load('projectCategory');

        return view('projects.show', compact('project'));
    }
}

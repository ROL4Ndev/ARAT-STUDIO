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
            ->whereHas('projects', fn ($query) => $query->published())
            ->orderBy('name')
            ->get();

        $projects = Project::published()
            ->with(['projectCategory', 'coverMedia'])
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
        abort_unless(Project::published()->whereKey($project->id)->exists(), 404);
        $project->load(['projectCategory', 'images' => fn ($query) => $query->where('is_active', true), 'coverMedia']);
        $related = Project::published()->with(['projectCategory', 'coverMedia'])
            ->where('category_id', $project->category_id)->whereKeyNot($project->id)
            ->orderByDesc('is_featured')->orderBy('featured_order')->orderBy('id')->limit(3)->get();

        return view('projects.show', compact('project', 'related'));
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::query()
            ->withCount('projects')
            ->orderBy('name')
            ->paginate(15);

        return view('admin.categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('admin.categories.create');
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $name = $request->validated('name');

        Category::create([
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
        ]);

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'La categoría se creó correctamente.');
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.edit', compact('category'));
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $name = $request->validated('name');
        $changes = ['name' => $name];

        if ($name !== $category->name) {
            $changes['slug'] = $this->uniqueSlug($name, $category->getKey());
        }

        $category->update($changes);

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'La categoría se actualizó correctamente.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->projects()->exists()) {
            return back()->with('error', 'No se puede eliminar esta categoría porque tiene proyectos asociados.');
        }

        $category->delete();

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'La categoría se eliminó correctamente.');
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::limit(Str::slug($name) ?: 'categoria', 90, '');
        $slug = $base;
        $suffix = 2;

        while (Category::query()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '<>', $ignoreId))
            ->exists()) {
            $slug = Str::limit($base, 85, '').'-'.$suffix++;
        }

        return $slug;
    }
}

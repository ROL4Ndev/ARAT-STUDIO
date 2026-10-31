<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Project;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $projects = Project::query()
                ->whereNull('category_id')
                ->get();

            foreach ($projects as $project) {
                $name = trim($project->category ?? '');

                if ($name === '') {
                    $name = 'Sin categoría';
                }

                $category = Category::where('name', $name)->first();

                if (! $category) {
                    $baseSlug = Str::slug($name) ?: 'categoria';
                    $slug = $baseSlug;
                    $suffix = 2;

                    while (Category::where('slug', $slug)->exists()) {
                        $slug = $baseSlug . '-' . $suffix++;
                    }

                    $category = Category::create([
                        'name' => $name,
                        'slug' => $slug,
                    ]);
                }

                $project->update([
                    'category_id' => $category->id,
                ]);
            }
        });
    }
}
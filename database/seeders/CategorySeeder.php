<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $names = [
                'Diseño interior',
                'Arquitectura comercial',
                'Remodelación',
                'Sin categoría',
            ];

            if (Schema::hasColumn('projects', 'category')) {
                $names = array_merge(
                    $names,
                    DB::table('projects')
                        ->whereNull('category_id')
                        ->pluck('category')
                        ->map(fn ($name) => trim((string) $name))
                        ->filter()
                        ->all()
                );
            }

            foreach (array_unique($names) as $name) {
                Category::firstOrCreate(
                    ['name' => $name],
                    ['slug' => $this->uniqueSlug($name)]
                );
            }

            $fallback = Category::firstOrCreate(
                ['name' => 'Sin categoría'],
                ['slug' => $this->uniqueSlug('Sin categoría')]
            );

            DB::table('projects')
                ->whereNull('category_id')
                ->orderBy('id')
                ->chunkById(100, function ($projects) use ($fallback): void {
                    foreach ($projects as $project) {
                        $name = 'Sin categoría';

                        if (Schema::hasColumn('projects', 'category')) {
                            $name = trim((string) ($project->category ?? '')) ?: $name;
                        }

                        $category = Category::query()
                            ->where('name', $name)
                            ->first() ?? $fallback;

                        DB::table('projects')
                            ->where('id', $project->id)
                            ->update(['category_id' => $category->id]);
                    }
                });
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'categoria';
        $slug = $base;
        $suffix = 2;

        while (Category::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('projects', 'category')) {
            return;
        }

        DB::table('projects')
            ->select(['id', 'category', 'category_id'])
            ->orderBy('id')
            ->chunkById(100, function ($projects): void {
                foreach ($projects as $project) {
                    if ($project->category_id !== null) {
                        continue;
                    }

                    $name = trim((string) $project->category);
                    $name = $name !== '' ? $name : 'Sin categoría';

                    $category = DB::table('categories')
                        ->where('name', $name)
                        ->first();

                    if ($category === null) {
                        $baseSlug = Str::slug($name) ?: 'categoria';
                        $slug = $baseSlug;
                        $suffix = 2;

                        while (DB::table('categories')->where('slug', $slug)->exists()) {
                            $slug = $baseSlug.'-'.$suffix++;
                        }

                        $now = now();
                        $categoryId = DB::table('categories')->insertGetId([
                            'name' => $name,
                            'slug' => $slug,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    } else {
                        $categoryId = $category->id;
                    }

                    DB::table('projects')
                        ->where('id', $project->id)
                        ->update(['category_id' => $categoryId]);
                }
            });

        Schema::table('projects', function (Blueprint $table): void {
            $table->foreignId('category_id')->nullable(false)->change();
            $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('projects', 'category')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table): void {
            $table->string('category')->nullable();
        });

        DB::table('projects')
            ->leftJoin('categories', 'projects.category_id', '=', 'categories.id')
            ->select(['projects.id', 'categories.name'])
            ->orderBy('projects.id')
            ->chunkById(100, function ($projects): void {
                foreach ($projects as $project) {
                    DB::table('projects')
                        ->where('id', $project->id)
                        ->update(['category' => $project->name ?? 'Sin categoría']);
                }
            }, 'projects.id');

        Schema::table('projects', function (Blueprint $table): void {
            $table->foreignId('category_id')->nullable()->change();
        });
    }
};

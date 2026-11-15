<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('path');
            $table->string('thumbnail_path')->nullable();
            $table->string('sha256', 64);
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'sha256']);
            $table->index(['project_id', 'is_active', 'sort_order'], 'gallery_project_order');
        });
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('featured_order')->default(0);
            $table->foreignId('cover_image_id')->nullable()->constrained('project_images')->restrictOnDelete();
            $table->index(['is_featured', 'is_active', 'featured_order'], 'projects_featured_order');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['cover_image_id']);
            $table->dropIndex('projects_featured_order');
            $table->dropColumn(['is_featured', 'featured_order', 'cover_image_id']);
        });
        Schema::dropIfExists('project_images');
    }
};

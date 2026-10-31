<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('categories')
                ->restrictOnDelete();

            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn('is_active');
        });
    }
};
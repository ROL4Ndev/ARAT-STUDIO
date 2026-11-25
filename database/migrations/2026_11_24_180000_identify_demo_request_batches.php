<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->string('demo_batch', 60)->nullable()->index();
            $table->string('demo_scenario', 40)->nullable();
            $table->unique(['demo_batch', 'demo_scenario']);
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropUnique(['demo_batch', 'demo_scenario']);
            $table->dropIndex(['demo_batch']);
            $table->dropColumn(['demo_batch', 'demo_scenario']);
        });
    }
};

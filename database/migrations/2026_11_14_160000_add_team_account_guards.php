<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('lock_version')->default(0);
            $table->unsignedInteger('auth_version')->default(0);
        });
        Schema::table('service_requests', function (Blueprint $table) {
            $table->index(['assigned_to', 'status'], 'requests_assignee_status');
            $table->index(['assigned_to', 'created_at'], 'requests_assignee_created');
            $table->index(['created_at', 'status'], 'requests_created_status');
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropIndex('requests_assignee_status');
            $table->dropIndex('requests_assignee_created');
            $table->dropIndex('requests_created_status');
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['lock_version', 'auth_version']));
    }
};

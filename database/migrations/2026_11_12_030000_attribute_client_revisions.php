<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_revisions', function (Blueprint $table) {
            $table->foreignId('client_grant_id')->nullable()->constrained('client_grants')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Las revisiones históricas no se eliminan; utilizar el respaldo verificado.');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', fn (Blueprint $t) => $t->foreignId('observed_revision_id')->nullable()->constrained('request_revisions')->restrictOnDelete());
        Schema::table('notification_records', fn (Blueprint $t) => $t->unique(['kind', 'resource_id', 'recipient'], 'notification_delivery_identity'));
    }

    public function down(): void
    {
        throw new RuntimeException('Conservar datos y restaurar solo copias aisladas.');
    }
};

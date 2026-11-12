<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dateTime('closed_at', 6)->nullable();
            $table->text('closure_reason')->nullable();
        });
        Schema::table('quotations', fn (Blueprint $table) => $table->string('pdf_hash', 64)->nullable());
        Schema::table('project_cover_histories', fn (Blueprint $table) => $table->string('retired_source_path')->nullable());
        Schema::table('request_revisions', fn (Blueprint $table) => $table->text('evidence')->nullable());
        Schema::table('service_request_histories', function (Blueprint $table) {
            $table->foreignId('client_grant_id')->nullable()->constrained('client_grants')->restrictOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->restrictOnDelete();
            $table->json('metadata')->nullable();
        });
        Schema::create('notification_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_record_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('attempt_no');
            $table->string('destination');
            $table->string('status', 20);
            $table->string('provider_reference')->nullable();
            $table->string('error')->nullable();
            $table->dateTime('attempted_at', 6);
            $table->unique(['notification_record_id', 'attempt_no']);
        });
        // No se inventan intentos anteriores: el agregado heredado se conserva.
        foreach (DB::table('quotations')->whereNotNull('pdf_path')->get() as $quotation) {
            if (Storage::disk('commercial')->exists($quotation->pdf_path)) {
                DB::table('quotations')->where('id', $quotation->id)->update(['pdf_hash' => hash_file('sha256', Storage::disk('commercial')->path($quotation->pdf_path))]);
            }
        }
        Schema::table('service_requests', fn (Blueprint $table) => $table->index(['status', 'assigned_to', 'created_at'], 'requests_status_assignee_created'));
        Schema::table('quotations', fn (Blueprint $table) => $table->index(['status', 'expires_at'], 'quotes_status_expiry'));
        Schema::table('services', fn (Blueprint $table) => $table->index(['is_active', 'display_order'], 'services_active_order'));
        Schema::table('projects', fn (Blueprint $table) => $table->index(['is_active', 'category_id'], 'projects_active_category'));
    }

    public function down(): void
    {
        throw new RuntimeException('La auditoría de entrega se conserva; utilizar restauración conciliada.');
    }
};

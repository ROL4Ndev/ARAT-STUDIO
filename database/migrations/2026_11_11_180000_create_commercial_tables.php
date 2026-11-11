<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->uuid('submission_token')->unique();
            $table->string('client_name', 150);
            $table->string('client_email');
            $table->string('client_phone', 30);
            $table->string('city', 120);
            $table->string('service', 80);
            $table->string('project_type', 80);
            $table->text('description');
            $table->decimal('area_m2', 12, 2);
            $table->string('lod_selected', 20);
            $table->decimal('estimated_cost', 18, 2)->nullable();
            $table->char('currency', 3)->default('PEN');
            $table->boolean('custom_evaluation');
            $table->decimal('base_price_m2', 12, 2);
            $table->decimal('lod_factor', 8, 3);
            $table->decimal('max_area_m2', 12, 2);
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime', 100)->nullable();
            $table->text('external_url')->nullable();
            $table->string('status', 20)->default('pendiente')->index();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('consented_at');
            $table->timestamps();
        });
        Schema::create('service_request_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40);
            $table->string('previous_status', 20)->nullable();
            $table->string('new_status', 20);
            $table->text('observation')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->foreignId('service_request_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('scope');
            $table->decimal('official_amount', 18, 2);
            $table->char('currency', 3)->default('PEN');
            $table->json('client_snapshot');
            $table->json('studio_snapshot');
            $table->date('issued_at');
            $table->date('expires_at');
            $table->string('pdf_path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('service_request_histories');
        Schema::dropIfExists('service_requests');
    }
};

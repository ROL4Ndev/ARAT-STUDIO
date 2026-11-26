<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliverables', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->foreignId('quotation_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['borrador', 'en_revision', 'observado', 'conforme'])->default('borrador');
            $table->unsignedInteger('lock_version')->default(0);
            $table->uuid('creation_key')->unique();
            $table->char('creation_hash', 64);
            $table->timestamps();
        });
        Schema::create('deliverable_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deliverable_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('number');
            $table->string('title', 160);
            $table->text('description');
            $table->text('internal_note')->nullable();
            $table->string('path')->nullable();
            $table->string('original_name', 200)->nullable();
            $table->string('mime', 40)->nullable();
            $table->unsignedInteger('bytes')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('sent_at', 6)->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->enum('decision', ['observado', 'conforme'])->nullable();
            $table->text('comment')->nullable();
            $table->foreignId('client_grant_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('responded_at', 6)->nullable();
            $table->uuid('save_key')->unique();
            $table->char('content_hash', 64);
            $table->timestamps();
            $table->unique(['deliverable_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliverable_versions');
        Schema::dropIfExists('deliverables');
    }
};

<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->boolean('is_active')->default(true));
        foreach (['categories', 'projects', 'tariff_params'] as $name) {
            Schema::table($name, function (Blueprint $t) use ($name) {
                if ($name !== 'projects') {
                    $t->boolean('is_active')->default(true);
                }
                $t->unsignedInteger('lock_version')->default(0);
                $t->text('status_reason')->nullable();
                $t->timestamp('deactivated_at')->nullable();
                $t->foreignId('deactivated_by')->nullable()->constrained('users')->restrictOnDelete();
            });
        }
        Schema::table('categories', fn (Blueprint $t) => $t->text('description')->nullable());
        Schema::create('services', function (Blueprint $t) {
            $t->id();
            $t->string('code', 60)->unique();
            $t->string('name', 150);
            $t->text('description');
            $t->string('audience', 20)->default('general');
            $t->unsignedInteger('display_order')->default(0);
            $t->string('estimation_mode', 20)->default('manual');
            $t->boolean('is_active')->default(false);
            $t->unsignedInteger('lock_version')->default(0);
            $t->text('status_reason')->nullable();
            $t->timestamp('deactivated_at')->nullable();
            $t->foreignId('deactivated_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        foreach (['ARQUITECTURA' => 'Arquitectura', 'INTERIORES' => 'Diseño de interiores', 'BIM' => 'Modelado BIM', 'VISUALIZACION' => 'Visualización 3D'] as $code => $name) {
            DB::table('services')->insert(['code' => $code, 'name' => $name, 'description' => 'Servicio operativo de '.$name.'. El alcance se define mediante evaluación comercial.', 'estimation_mode' => 'area_lod', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        $i = 0;
        foreach (config('studio.services', []) as $group => $items) {
            foreach ($items as $item) {
                DB::table('services')->insert(['code' => 'HOME_'.(++$i), 'name' => $item['name'], 'description' => $item['description'], 'audience' => str_contains($group, 'estudiantes') ? 'estudiantes' : 'general', 'display_order' => $i, 'estimation_mode' => 'manual', 'is_active' => false, 'status_reason' => 'Borrador importado del Home; sin equivalencia exacta comprobada.', 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        Schema::create('tariff_groups', function (Blueprint $t) {
            $t->id();
            $t->string('lod_level', 20)->unique();
            $t->unsignedBigInteger('active_tariff_id')->nullable();
            $t->timestamps();
        });
        Schema::table('tariff_params', function (Blueprint $t) {
            $t->dropUnique(['lod_level']);
            $t->foreignId('tariff_group_id')->nullable()->constrained()->restrictOnDelete();
            $t->unsignedInteger('version')->default(1);
            $t->string('status', 20)->default('activa');
            $t->char('currency', 3)->default('PEN');
            $t->text('version_reason')->nullable();
            $t->decimal('lod_factor', 8, 3)->change();
            $t->unique(['tariff_group_id', 'version']);
        });
        foreach (DB::table('tariff_params')->orderBy('id')->get() as $tariff) {
            $group = DB::table('tariff_groups')->insertGetId(['lod_level' => $tariff->lod_level, 'active_tariff_id' => $tariff->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('tariff_params')->where('id', $tariff->id)->update(['tariff_group_id' => $group]);
        }
        Schema::table('tariff_groups', fn (Blueprint $t) => $t->foreign('active_tariff_id')->references('id')->on('tariff_params')->restrictOnDelete());
        Schema::create('audit_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('actor_type', 30);
            $t->string('resource_type', 80);
            $t->unsignedBigInteger('resource_id');
            $t->string('action', 60);
            $t->text('reason');
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->dateTime('occurred_at', 6);
            $t->index(['resource_type', 'resource_id']);
        });
        Schema::create('project_cover_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('private_path');
            $t->string('source_path');
            $t->string('mime', 100);
            $t->unsignedBigInteger('size');
            $t->string('sha256', 64);
            $t->timestamp('created_at');
        });
        foreach (DB::table('projects')->get() as $project) {
            $source = str_starts_with($project->cover_image, 'img/') ? public_path($project->cover_image) : Storage::disk('public')->path($project->cover_image);
            if (! is_file($source)) {
                throw new RuntimeException('Portada heredada ausente; revisar respaldo antes de continuar: proyecto '.$project->id);
            }
            $path = 'covers/'.Str::uuid().'.'.pathinfo($source, PATHINFO_EXTENSION);
            if (! Storage::disk('commercial')->put($path, file_get_contents($source))) {
                throw new RuntimeException('No se pudo preservar una portada heredada.');
            }
            DB::table('project_cover_histories')->insert(['project_id' => $project->id, 'private_path' => $path, 'source_path' => $project->cover_image, 'mime' => mime_content_type($source), 'size' => filesize($source), 'sha256' => hash_file('sha256', $source), 'created_at' => now()]);
        }
        Schema::table('service_requests', function (Blueprint $t) {
            $t->decimal('area_m2', 12, 2)->nullable()->change();
            $t->string('lod_selected', 20)->nullable()->change();
            $t->decimal('base_price_m2', 12, 2)->nullable()->change();
            $t->decimal('lod_factor', 8, 3)->nullable()->change();
            $t->decimal('max_area_m2', 12, 2)->nullable()->change();
            $t->foreignId('service_id')->nullable()->constrained('services')->restrictOnDelete();
            $t->json('service_snapshot')->nullable();
            $t->json('tariff_snapshot')->nullable();
            $t->foreignId('tariff_param_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('request_hash', 64)->nullable();
            $t->text('estimation_reason')->nullable();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->restrictOnDelete();
            $t->unsignedInteger('lock_version')->default(0);
            $t->foreignId('related_request_id')->nullable()->constrained('service_requests')->restrictOnDelete();
            $t->unsignedBigInteger('current_revision_id')->nullable();
            $t->unsignedBigInteger('current_quotation_id')->nullable();
            $t->dateTime('first_evaluated_at', 6)->nullable();
            $t->string('closure_outcome', 30)->nullable();
        });
        Schema::table('service_request_histories', fn (Blueprint $t) => $t->boolean('is_public')->default(false));
        Schema::create('request_revisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('service_request_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('version');
            $t->string('author_type', 30);
            $t->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('kind', 30);
            $t->json('before')->nullable();
            $t->json('after');
            $t->timestamp('created_at');
            $t->unique(['service_request_id', 'version']);
        });
        Schema::create('request_files', function (Blueprint $t) {
            $t->id();
            $t->foreignId('service_request_id')->constrained()->restrictOnDelete();
            $t->foreignId('request_revision_id')->constrained('request_revisions')->restrictOnDelete();
            $t->string('path');
            $t->string('original_name');
            $t->string('mime', 100);
            $t->unsignedBigInteger('size')->nullable();
            $t->string('sha256', 64)->nullable();
            $t->timestamp('created_at');
        });
        foreach (DB::table('service_requests')->orderBy('id')->get() as $request) {
            $service = DB::table('services')->where('name', $request->service)->first();
            $changes = ['service_id' => $service?->id, 'service_snapshot' => json_encode(['code' => $service?->code, 'name' => $request->service, 'estimation_mode' => 'area_lod']), 'tariff_snapshot' => json_encode(['base_price_m2' => $request->base_price_m2, 'lod_factor' => $request->lod_factor, 'max_area_m2' => $request->max_area_m2, 'currency' => $request->currency]), 'estimation_reason' => $request->custom_evaluation ? 'Área fuera del límite automático.' : null];
            $revision = DB::table('request_revisions')->insertGetId(['service_request_id' => $request->id, 'version' => 1, 'author_type' => 'legacy', 'kind' => 'initial', 'after' => json_encode(array_merge((array) $request, $changes)), 'created_at' => $request->created_at]);
            DB::table('service_requests')->where('id', $request->id)->update($changes + ['current_revision_id' => $revision]);
            if ($request->attachment_path) {
                $disk = Storage::disk('commercial');
                DB::table('request_files')->insert(['service_request_id' => $request->id, 'request_revision_id' => $revision, 'path' => $request->attachment_path, 'original_name' => $request->attachment_name ?? 'Adjunto', 'mime' => $request->attachment_mime ?? 'application/octet-stream', 'size' => $disk->exists($request->attachment_path) ? $disk->size($request->attachment_path) : null, 'sha256' => $disk->exists($request->attachment_path) ? hash_file('sha256', $disk->path($request->attachment_path)) : null, 'created_at' => $request->created_at]);
            }
        }
        Schema::create('technical_evaluations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('service_request_id')->constrained()->restrictOnDelete();
            $t->foreignId('request_revision_id')->constrained('request_revisions')->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->text('assessment');
            $t->timestamp('created_at');
        });
        Schema::table('quotations', function (Blueprint $t) {
            $t->index('service_request_id', 'quotations_request_lookup');
            $t->dropUnique(['service_request_id']);
            $t->unsignedInteger('version')->default(1);
            $t->unique(['service_request_id', 'version']);
            $t->foreignId('request_revision_id')->nullable()->constrained('request_revisions')->restrictOnDelete();
            $t->string('status', 20)->default('emitida');
            $t->string('pdf_status', 20)->default('ready');
            $t->string('notification_status', 20)->default('pending');
            $t->unsignedInteger('lock_version')->default(0);
            $t->json('price_snapshot')->nullable();
            $t->text('deliverables')->nullable();
            $t->text('exclusions')->nullable();
            $t->string('execution_term')->nullable();
            $t->text('adjustment_reason')->nullable();
            $t->text('version_reason')->nullable();
            $t->text('technical_error')->nullable();
            $t->text('response_reason')->nullable();
            $t->dateTime('responded_at', 6)->nullable();
            $t->dateTime('issued_at', 6)->nullable()->change();
            $t->dateTime('expires_at', 6)->nullable()->change();
            $t->string('pdf_path')->nullable()->change();
        });
        foreach (DB::table('quotations')->get() as $quotation) {
            $request = DB::table('service_requests')->find($quotation->service_request_id);
            DB::table('quotations')->where('id', $quotation->id)->update(['request_revision_id' => $request->current_revision_id, 'version_reason' => 'Migración conservadora de cotización existente a versión 1.', 'issued_at' => CarbonImmutable::parse($quotation->issued_at, 'America/Lima')->startOfDay()->utc()->format('Y-m-d H:i:s.u'), 'expires_at' => CarbonImmutable::parse($quotation->expires_at, 'America/Lima')->endOfDay()->utc()->format('Y-m-d H:i:s.u'), 'price_snapshot' => json_encode(['estimated_cost' => $request->estimated_cost, 'currency' => $quotation->currency, 'legacy' => true])]);
            DB::table('service_requests')->where('id', $request->id)->update(['current_quotation_id' => $quotation->id]);
        }
        Schema::table('service_requests', function (Blueprint $t) {
            $t->foreign('current_revision_id')->references('id')->on('request_revisions')->restrictOnDelete();
            $t->foreign('current_quotation_id')->references('id')->on('quotations')->restrictOnDelete();
        });
        Schema::create('work_intents', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->string('kind', 30);
            $t->unsignedBigInteger('resource_id');
            $t->string('status', 20)->default('pending');
            $t->unsignedInteger('attempts')->default(0);
            $t->string('lease')->nullable();
            $t->dateTime('leased_at', 6)->nullable();
            $t->text('error')->nullable();
            $t->json('result')->nullable();
            $t->timestamps();
        });
        Schema::create('notification_records', function (Blueprint $t) {
            $t->id();
            $t->string('kind', 40);
            $t->unsignedBigInteger('resource_id');
            $t->string('recipient');
            $t->string('status', 20)->default('pending');
            $t->unsignedInteger('attempts')->default(0);
            $t->text('error')->nullable();
            $t->string('provider_reference')->nullable();
            $t->timestamps();
        });
        Schema::create('client_access_tokens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('service_request_id')->constrained()->restrictOnDelete();
            $t->string('email');
            $t->string('purpose', 30)->default('access');
            $t->string('token_hash', 64)->unique();
            $t->dateTime('expires_at', 6);
            $t->dateTime('consumed_at', 6)->nullable();
            $t->timestamps();
        });
        Schema::create('client_grants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('service_request_id')->constrained()->restrictOnDelete();
            $t->string('email');
            $t->string('session_hash', 64)->unique();
            $t->dateTime('expires_at', 6);
            $t->dateTime('revoked_at', 6)->nullable();
            $t->timestamps();
        });
        // Append-only también en BD; no contiene tokens ni contraseñas.
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared("CREATE TRIGGER audit_events_no_update BEFORE UPDATE ON audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Auditoria inmutable'");
            DB::unprepared("CREATE TRIGGER audit_events_no_delete BEFORE DELETE ON audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Auditoria inmutable'");
        } else {
            DB::unprepared("CREATE TRIGGER audit_events_no_update BEFORE UPDATE ON audit_events BEGIN SELECT RAISE(ABORT, 'Auditoria inmutable'); END");
            DB::unprepared("CREATE TRIGGER audit_events_no_delete BEFORE DELETE ON audit_events BEGIN SELECT RAISE(ABORT, 'Auditoria inmutable'); END");
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Migración conservadora: restaurar una copia aislada; no borrar datos de negocio.');
    }
};

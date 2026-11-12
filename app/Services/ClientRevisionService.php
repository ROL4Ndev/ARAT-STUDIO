<?php

namespace App\Services;

use App\Models\ClientGrant;
use App\Models\OfferedService;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Policies\ServiceRequestPolicy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClientRevisionService
{
    public function __construct(private AuditService $audit, private QuotationCalculatorService $calculator, private NotificationService $notifications) {}

    public function correct(ServiceRequest $record, ClientGrant|User $grant, array $data, ?UploadedFile $file): void
    {
        $path = null;
        try {
            if ($file) {
                $path = 'attachments/'.Str::uuid().'.'.strtolower($file->getClientOriginalExtension());
                if (! Storage::disk('commercial')->putFileAs('attachments', $file, basename($path))) {
                    throw new \RuntimeException('No se pudo guardar el adjunto.');
                }
            }
            DB::transaction(function () use ($record, $grant, $data, $file, $path) {
                $r = DomainLock::get($record, (int) $data['lock_version']);
                if ($grant instanceof User) {
                    Gate::forUser($grant)->authorize('operate', $r);
                    abort_if(mb_strlen(trim($data['evidence'] ?? '')) < 20, 422, 'Registra la evidencia que respalda la corrección.');
                    foreach (['client_name', 'client_phone', 'city'] as $contact) {
                        abort_if(isset($data[$contact]) && $data[$contact] !== $r->$contact, 422, 'El contacto requiere corrección del cliente verificado.');
                    }
                } else {
                    abort_unless(app(ServiceRequestPolicy::class)->clientView($grant, $r), 403);
                }
                abort_unless($r->status === 'observada', 409, 'Solo puede corregirse una solicitud observada.');
                abort_if(isset($data['service_id']) || isset($data['client_email']) || isset($data['service']), 422, 'El servicio no se cambia por corrección y el correo requiere verificación.');
                $before = Arr::except($r->toArray(), ['submission_token', 'request_hash']);
                $values = Arr::only($data, ['client_name', 'client_phone', 'city', 'project_type', 'description', 'area_m2', 'lod_selected', 'external_url']);
                if ($grant instanceof User) {
                    $values = Arr::except($values, ['client_name', 'client_phone', 'city']);
                }
                $service = new OfferedService(['id' => $r->service_id, 'code' => $r->service_snapshot['code'], 'name' => $r->service_snapshot['name'], 'description' => $r->service_snapshot['description'] ?? 'Servicio histórico', 'estimation_mode' => $r->service_snapshot['estimation_mode']]);
                if ($service->estimation_mode === 'manual') {
                    $values['area_m2'] = null;
                    $values['lod_selected'] = null;
                }
                $e = $this->calculator->calculate($values['area_m2'] ?? null, $values['lod_selected'] ?? null, $service);
                $r->fill($values + ['estimated_cost' => $e['estimated_cost_decimal'], 'tariff_snapshot' => $e['tariff_snapshot'], 'tariff_param_id' => $e['tariff_snapshot']['id'], 'base_price_m2' => $e['base_price_m2'], 'lod_factor' => $e['lod_factor'], 'max_area_m2' => $e['max_area_m2'], 'custom_evaluation' => $e['custom_evaluation'], 'estimation_reason' => $e['reason']]);
                if ($path) {
                    $r->fill(['attachment_path' => $path, 'attachment_name' => $file->getClientOriginalName(), 'attachment_mime' => $file->getMimeType()]);
                }
                $r->status = 'evaluacion';
                $r->lock_version++;
                $r->save();
                $revision = $r->revisions()->create(['version' => (int) $r->revisions()->max('version') + 1, 'author_type' => $grant instanceof User ? 'staff' : 'client', 'user_id' => $grant instanceof User ? $grant->id : null, 'client_grant_id' => $grant instanceof ClientGrant ? $grant->id : null, 'evidence' => $grant instanceof User ? $data['evidence'] : null, 'kind' => 'correction', 'before' => $before, 'after' => Arr::except($r->toArray(), ['submission_token', 'request_hash']), 'created_at' => now('UTC')]);
                $r->update(['current_revision_id' => $revision->id]);
                if ($path) {
                    $r->files()->create(['request_revision_id' => $revision->id, 'path' => $path, 'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 240), 'mime' => $file->getMimeType(), 'size' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath()), 'created_at' => now('UTC')]);
                    $r->update(['attachment_path' => $path, 'attachment_name' => $file->getClientOriginalName(), 'attachment_mime' => $file->getMimeType()]);
                }
                $r->histories()->create(['action' => 'correction', 'user_id' => $grant instanceof User ? $grant->id : null, 'client_grant_id' => $grant instanceof ClientGrant ? $grant->id : null, 'metadata' => ['revision_id' => $revision->id, 'lock_version' => $r->lock_version], 'previous_status' => 'observada', 'new_status' => 'evaluacion', 'is_public' => true, 'observation' => $grant instanceof User ? 'El responsable registró una corrección con evidencia; volvió a evaluación.' : $data['reason']]);
                if ($r->assignee?->is_active) {
                    $this->notifications->queue('corrected', $revision->id, $r->assignee->email);
                }
                $this->audit->record($r, 'client_correction', $data['reason'], $before, Arr::except($r->toArray(), ['submission_token', 'request_hash']), $grant);
            }, 3);
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('commercial')->delete($path);
            }throw $e;
        }
    }
}

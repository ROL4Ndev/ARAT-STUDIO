<?php

namespace App\Services;

use App\Models\ClientGrant;
use App\Models\Deliverable;
use App\Models\DeliverableVersion;
use App\Models\Quotation;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Policies\ServiceRequestPolicy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeliverableWorkflowService
{
    public function __construct(private AuditService $audit) {}

    public function create(ServiceRequest $record, User $actor, array $data, ?UploadedFile $file = null): Deliverable
    {
        Gate::forUser($actor)->authorize('create', [Deliverable::class, $record]);
        $data = $this->content($data);
        $attachment = $this->attachment($file);
        $hash = $this->digest($data, $attachment['sha256'] ?? null);
        $stored = false;
        try {
            $result = DB::transaction(function () use ($record, $actor, $data, $attachment, $hash, &$stored) {
                [$request, $user] = $this->staff($record->id, $actor);
                $quote = $request->quotations()->where('code', $data['quotation_code'] ?? '')->lockForUpdate()->first();
                abort_unless($quote && $quote->status === 'aceptada', 409, 'Selecciona una cotización aceptada de este expediente.');
                if ($existing = Deliverable::where('creation_key', $data['operation_key'])->lockForUpdate()->first()) {
                    abort_unless($existing->quotation_id === $quote->id && hash_equals($existing->creation_hash, $hash), 409, 'La operación ya se utilizó con otros datos.');

                    return $existing;
                }
                $deliverable = Deliverable::create(['code' => 'ENT-'.Str::ulid(), 'quotation_id' => $quote->id,
                    'user_id' => $user->id, 'status' => 'borrador', 'creation_key' => $data['operation_key'], 'creation_hash' => $hash]);
                $values = $this->versionContent($data) + ['number' => 1, 'user_id' => $user->id,
                    'save_key' => $data['operation_key'], 'content_hash' => $hash];
                if ($attachment) {
                    $stored = true;
                    Storage::disk('commercial')->put($attachment['path'], $attachment['contents']);
                    $values += $this->fileMetadata($attachment);
                }
                $deliverable->versions()->create($values);
                $this->audit->record($deliverable, 'deliverable_created', 'Borrador de entregable registrado.', [], ['code' => $deliverable->code, 'quotation' => $quote->code], $user);

                return $deliverable;
            }, 3);
        } catch (\Throwable $error) {
            if ($stored) $this->discardUnreferenced($attachment['path']);
            throw $error;
        }
        if ($stored) $this->discardUnreferenced($attachment['path']);

        return $result;
    }

    public function saveDraft(Deliverable $record, User $actor, array $data, ?UploadedFile $file = null): DeliverableVersion
    {
        Gate::forUser($actor)->authorize('view', $record);
        $data = $this->content($data);
        $attachment = $this->attachment($file);
        $stored = false;
        $oldPath = null;
        try {
            $result = DB::transaction(function () use ($record, $actor, $data, $attachment, &$stored, &$oldPath) {
                [, $user] = $this->staff($record->quotation->service_request_id, $actor);
                $deliverable = $this->locked($record);
                if ($repeat = DeliverableVersion::where('save_key', $data['operation_key'])->lockForUpdate()->first()) {
                    abort_unless($repeat->deliverable_id === $deliverable->id && hash_equals($repeat->content_hash,
                        $this->digest($data, $attachment['sha256'] ?? $repeat->sha256)), 409, 'La operación ya se utilizó con otros datos.');

                    return $repeat;
                }
                abort_unless(in_array($deliverable->status, ['borrador', 'observado'], true), 409, 'Solo puedes preparar otra versión después de una observación. Un entregable conforme es de solo lectura.');
                $this->expected($deliverable, $data['lock_version'] ?? -1);
                $version = $deliverable->versions()->reorder()->latest('number')->lockForUpdate()->firstOrFail();
                $new = $version->sent_at !== null;
                $values = $this->versionContent($data) + ['save_key' => $data['operation_key'],
                    'content_hash' => $this->digest($data, $attachment['sha256'] ?? ($new ? null : $version->sha256))];
                if ($attachment) {
                    $stored = true;
                    Storage::disk('commercial')->put($attachment['path'], $attachment['contents']);
                    $values += $this->fileMetadata($attachment);
                    if (! $new) $oldPath = $version->path;
                }
                if ($new) {
                    $version = $deliverable->versions()->create($values + ['number' => $version->number + 1, 'user_id' => $user->id]);
                } else {
                    $version->update($values);
                }
                $deliverable->increment('lock_version');
                $this->audit->record($deliverable, 'deliverable_draft_saved', 'Versión en borrador guardada.', [], ['version' => $version->number, 'title' => $version->title], $user);

                return $version;
            }, 3);
        } catch (\Throwable $error) {
            if ($stored) $this->discardUnreferenced($attachment['path']);
            throw $error;
        }
        if ($stored) $this->discardUnreferenced($attachment['path']);
        if ($oldPath && $oldPath !== $result->path) {
            // Solo se sustituye el archivo de un borrador, después del commit.
            $this->discardUnreferenced($oldPath);
        }

        return $result;
    }

    public function send(Deliverable $record, User $actor, int $number, int $expected): DeliverableVersion
    {
        return DB::transaction(function () use ($record, $actor, $number, $expected) {
            [, $user] = $this->staff($record->quotation->service_request_id, $actor);
            $deliverable = $this->locked($record);
            $version = $deliverable->versions()->where('number', $number)->lockForUpdate()->firstOrFail();
            if ($version->sent_at !== null) return $version;
            abort_unless(in_array($deliverable->status, ['borrador', 'observado'], true), 409, 'El entregable ya no permite enviar esta versión.');
            $this->expected($deliverable, $expected);
            abort_unless($number === (int) $deliverable->versions()->reorder()->orderByDesc('number')->lockForUpdate()->value('number'), 409, 'Esta versión ya no es la última.');
            $this->verifyFile($version);
            $version->update(['sent_at' => now('UTC'), 'sent_by' => $user->id]);
            $deliverable->update(['status' => 'en_revision', 'lock_version' => $deliverable->lock_version + 1]);
            $this->audit->record($deliverable, 'deliverable_sent', 'Versión enviada a revisión del cliente.', [], ['version' => $number, 'sha256' => $version->sha256], $user);

            return $version;
        }, 3);
    }

    public function respond(Deliverable $record, ClientGrant $actor, int $number, array $data): DeliverableVersion
    {
        Validator::make($data, ['decision' => 'required|in:observado,conforme', 'comment' => 'nullable|string|max:5000', 'lock_version' => 'required|integer|min:0'])->validate();
        $comment = trim($data['comment'] ?? '') ?: null;
        if ($data['decision'] === 'observado' && $comment === null) {
            throw ValidationException::withMessages(['comment' => 'Describe qué debe revisarse en el documento.']);
        }
        if ($data['decision'] === 'conforme' && ! filter_var($data['confirmation'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            throw ValidationException::withMessages(['confirmation' => 'Confirma que revisaste esta versión antes de dar conformidad.']);
        }

        return DB::transaction(function () use ($record, $actor, $number, $data, $comment) {
            $request = ServiceRequest::whereKey($record->quotation->service_request_id)->lockForUpdate()->firstOrFail();
            $deliverable = $this->locked($record);
            $version = $deliverable->versions()->where('number', $number)->lockForUpdate()->firstOrFail();
            $grant = ClientGrant::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($grant->service_request_id === $request->id && hash_equals($request->client_email, $grant->email)
                && ! $grant->revoked_at && $grant->expires_at->isFuture()
                && app(ServiceRequestPolicy::class)->clientView($grant, $request), 403, 'El acceso temporal no corresponde a este expediente.');
            $published = $deliverable->versions()->whereNotNull('sent_at')->reorder()->orderByDesc('number')->lockForUpdate()->value('number');
            abort_unless($version->sent_at !== null && $number === (int) $published, 409, 'Solo puedes responder a la versión vigente enviada.');
            if ($version->decision !== null) {
                abort_unless($version->decision === $data['decision'] && $version->comment === $comment, 409, 'Ya existe una respuesta distinta para esta versión.');

                return $version;
            }
            abort_unless($deliverable->status === 'en_revision', 409, 'El entregable no está pendiente de respuesta.');
            $this->expected($deliverable, $data['lock_version']);
            $version->update(['decision' => $data['decision'], 'comment' => $comment, 'client_grant_id' => $grant->id, 'responded_at' => now('UTC')]);
            $deliverable->update(['status' => $data['decision'], 'lock_version' => $deliverable->lock_version + 1]);
            $this->audit->record($deliverable, 'deliverable_client_response', 'Respuesta verificada del cliente.', [], ['version' => $number, 'decision' => $version->decision, 'comment' => $comment], $grant);

            return $version;
        }, 3);
    }

    private function staff(int $requestId, User $actor): array
    {
        $user = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
        $request = ServiceRequest::whereKey($requestId)->lockForUpdate()->firstOrFail();
        Gate::forUser($user)->authorize('operate', $request);

        return [$request, $user];
    }

    private function locked(Deliverable $record): Deliverable
    {
        $quote = Quotation::whereKey($record->quotation_id)->lockForUpdate()->firstOrFail();
        abort_unless($quote->status === 'aceptada', 409, 'El entregable requiere su cotización aceptada.');

        return Deliverable::whereKey($record->id)->lockForUpdate()->firstOrFail();
    }

    private function expected(Deliverable $record, int $version): void
    {
        abort_unless($record->lock_version === $version, 409, 'El entregable cambió. Recarga la página antes de continuar.');
    }

    private function content(array $data): array
    {
        foreach (['title', 'description', 'internal_note'] as $key) $data[$key] = trim($data[$key] ?? '');
        Validator::make($data, ['title' => 'required|string|max:160', 'description' => 'required|string|max:5000',
            'internal_note' => 'nullable|string|max:2000', 'operation_key' => 'required|uuid'])->validate();

        return $data;
    }

    private function versionContent(array $data): array
    {
        return ['title' => $data['title'], 'description' => $data['description'], 'internal_note' => $data['internal_note'] ?: null];
    }

    private function digest(array $data, ?string $fileHash): string
    {
        return hash('sha256', json_encode([$data['title'], $data['description'], $data['internal_note'], $fileHash], JSON_THROW_ON_ERROR));
    }

    private function attachment(?UploadedFile $file): ?array
    {
        if (! $file) return null;
        Validator::make(['attachment' => $file], ['attachment' => 'file|max:10240|mimes:pdf,png,jpg,jpeg|mimetypes:application/pdf,image/png,image/jpeg'],
            ['attachment.max' => 'El archivo debe pesar como máximo 10 MiB.', 'attachment.mimes' => 'Adjunta un PDF, PNG o JPEG válido.', 'attachment.mimetypes' => 'El contenido no corresponde a un PDF, PNG o JPEG.'])->validate();
        $mime = $file->getMimeType();
        $contents = $file->get();
        $valid = $mime === 'application/pdf'
            ? str_starts_with($contents, '%PDF-') && str_contains(substr($contents, -1024), '%%EOF')
            : @getimagesize($file->getRealPath()) !== false;
        $extension = ['application/pdf' => 'pdf', 'image/png' => 'png', 'image/jpeg' => 'jpg'][$mime] ?? null;
        $originalExtension = strtolower($file->getClientOriginalExtension());
        if (! $valid || ! $extension || ! in_array($originalExtension, $mime === 'image/jpeg' ? ['jpg', 'jpeg'] : [$extension], true)) {
            throw ValidationException::withMessages(['attachment' => 'El archivo está dañado o su extensión no corresponde al contenido.']);
        }
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $name = mb_substr(preg_replace('/[\x00-\x1f\x7f]/u', '', $name), 0, 200);

        return ['path' => 'deliverables/'.Str::uuid().'.'.$extension, 'original_name' => $name, 'mime' => $mime,
            'bytes' => strlen($contents), 'sha256' => hash('sha256', $contents), 'contents' => $contents];
    }

    private function fileMetadata(array $attachment): array
    {
        return array_diff_key($attachment, ['contents' => true]);
    }

    private function discardUnreferenced(string $path): void
    {
        // Una confirmación de commit incierta nunca debe provocar el borrado de un archivo conservado.
        try {
            if (str_starts_with($path, 'deliverables/') && ! DeliverableVersion::where('path', $path)->exists()) {
                Storage::disk('commercial')->delete($path);
            }
        } catch (\Throwable $error) { report($error); }
    }

    public function verifyFile(DeliverableVersion $version): void
    {
        $disk = Storage::disk('commercial');
        if (! $version->path || ! $disk->exists($version->path) || $version->bytes > 10 * 1024 * 1024
            || ! hash_equals($version->sha256 ?? '', hash('sha256', $disk->get($version->path)))) {
            throw ValidationException::withMessages(['attachment' => 'El archivo no está disponible o no conserva su integridad. Adjunta uno válido antes de enviarlo.']);
        }
    }
}

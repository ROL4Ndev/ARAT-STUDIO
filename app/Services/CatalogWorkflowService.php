<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Project;
use App\Models\ProjectCoverHistory;
use App\Models\TariffGroup;
use App\Models\TariffParam;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CatalogWorkflowService
{
    public function __construct(private AuditService $audit) {}

    public function save(string $kind, ?Model $record, array $data, ?UploadedFile $file, User $actor): Model
    {
        [, $class] = CatalogRegistry::get($kind);
        Gate::forUser($actor)->authorize($record ? 'update' : 'create', $record ?? $class);
        $uploaded = $file?->store('project-covers', 'public');
        if ($file && ! $uploaded) {
            throw new \RuntimeException('No se pudo guardar la portada.');
        }
        $relocations = [];
        try {
            return DB::transaction(function () use ($kind, $record, $data, $actor, $uploaded, $class, &$relocations) {
                $category = $kind === 'projects' ? Category::whereKey($data['category_id'])->lockForUpdate()->firstOrFail() : null;
                $group = $kind === 'tariffs' ? TariffGroup::firstOrCreate(['lod_level' => $record?->lod_level ?? $data['lod_level']]) : null;
                if ($group) {
                    $group = TariffGroup::whereKey($group->id)->lockForUpdate()->firstOrFail();
                }
                $locked = $record ? DomainLock::get($record, (int) $data['lock_version']) : new $class;
                $before = $locked->exists ? $locked->toArray() : [];
                $values = Arr::except($data, ['reason', 'lock_version', 'cover_image']);
                if ($kind === 'projects') {
                    if ($data['is_active'] && ! $category->is_active) {
                        throw ValidationException::withMessages(['category_id' => 'La categoría debe estar activa para publicar.']);
                    }
                    if (! $record) {
                        $values['slug'] = Str::slug($data['title']).'-'.Str::lower((string) Str::ulid());
                    }
                    if ($uploaded) {
                        $values['cover_image'] = $uploaded;
                    }
                }
                if ($kind === 'categories' && ! $record) {
                    $values += ['slug' => Str::slug($data['name']).'-'.Str::lower((string) Str::ulid()), 'is_active' => true];
                }
                if ($kind === 'services' && ! $record) {
                    $values['is_active'] = false;
                }
                if ($kind === 'tariffs') {
                    // Publicada: sus precios son inmutables; la edición crea borrador nuevo.
                    if ($locked->exists && $locked->status !== 'borrador') {
                        $locked = new TariffParam;
                    }
                    $values += ['lod_level' => $group->lod_level, 'tariff_group_id' => $group->id, 'currency' => 'PEN', 'version_reason' => $data['reason']];
                    if (! $locked->exists) {
                        $values += ['version' => (int) $group->versions()->max('version') + 1, 'status' => 'borrador', 'is_active' => false];
                    }
                }
                $locked->fill($values);
                $locked->lock_version = $locked->exists ? $locked->lock_version + 1 : 0;
                $locked->save();
                if ($uploaded) {
                    $this->archiveCover($locked, $uploaded, $actor);
                    if (isset($before['cover_image']) && $before['cover_image'] !== $uploaded) {
                        $this->retireCover($before['cover_image'], $relocations);
                    }
                }
                $this->audit->record($locked, $before ? 'catalog_edit' : 'catalog_create', $data['reason'], $before, $locked->toArray(), $actor);

                return $locked;
            }, $uploaded ? 1 : 3);
        } catch (\Throwable $e) {
            foreach (array_reverse($relocations) as [$from,$to]) {
                if (is_file($to) && ! is_file($from) && ! rename($to, $from)) {
                    throw new \RuntimeException('No se pudo restaurar la ubicación de la portada; la copia privada se conserva.', 0, $e);
                }
            }
            if ($uploaded) {
                Storage::disk('public')->delete($uploaded);
            } // Solo carga nueva no confirmada.
            throw $e;
        }
    }

    public function status(Model $record, bool $active, int $version, string $reason, User $actor, ?int $expectedActiveId = null, bool $compareActive = false): void
    {
        Gate::forUser($actor)->authorize('changeStatus', $record);
        DB::transaction(function () use ($record, $active, $version, $reason, $actor, $expectedActiveId, $compareActive) {
            $group = $record instanceof TariffParam ? TariffGroup::whereKey($record->tariff_group_id)->lockForUpdate()->firstOrFail() : null;
            if ($group && $compareActive) {
                abort_unless($group->active_tariff_id === $expectedActiveId, 409, 'Otra persona cambió la tarifa vigente. Recarga antes de confirmar.');
            }
            if ($record instanceof Project) {
                Category::whereKey($record->category_id)->lockForUpdate()->firstOrFail();
            }
            $locked = DomainLock::get($record, $version);
            $before = $locked->toArray();
            if ($locked instanceof Category && ! $active && $locked->projects()->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['active' => 'La categoría tiene proyectos activos. Revísalos en el detalle; no se desactivarán automáticamente.']);
            }
            if ($locked instanceof Project && $active && ! $locked->projectCategory->is_active) {
                throw ValidationException::withMessages(['active' => 'Activa la categoría antes de publicar.']);
            }
            if ($locked instanceof Project && $active) {
                Validator::make($locked->toArray(), ['title' => ['required', 'string', 'min:3', 'max:180'], 'description' => ['required', 'string', 'min:20', 'max:10000']])->validate();
                $exists = str_starts_with($locked->cover_image ?? '', 'img/') ? is_file(public_path($locked->cover_image)) : Storage::disk('public')->exists($locked->cover_image ?? '');
                if (! $exists) {
                    throw ValidationException::withMessages(['cover_image' => 'Completa una portada accesible antes de publicar o reactivar.']);
                }
            }
            if ($group && $active && $group->active_tariff_id && $group->active_tariff_id !== $locked->id) {
                $previous = TariffParam::whereKey($group->active_tariff_id)->lockForUpdate()->firstOrFail();
                abort_unless($previous->tariff_group_id === $group->id, 409, 'La tarifa vigente no pertenece a esta familia.');
                $old = $previous->toArray();
                $previous->update(['status' => 'inactiva', 'is_active' => false, 'lock_version' => $previous->lock_version + 1, 'status_reason' => $reason, 'deactivated_at' => now('UTC'), 'deactivated_by' => $actor->id]);
                $this->audit->record($previous, 'tariff_replaced', $reason, $old, $previous->toArray(), $actor);
            }
            $locked->fill(['is_active' => $active, 'status_reason' => $reason, 'deactivated_at' => $active ? null : now('UTC'), 'deactivated_by' => $active ? null : $actor->id, 'lock_version' => $locked->lock_version + 1]);
            if ($group) {
                abort_unless($locked->tariff_group_id === $group->id, 409);
                $locked->status = $active ? 'activa' : 'inactiva';
                if ($active || $group->active_tariff_id === $locked->id) {
                    $group->update(['active_tariff_id' => $active ? $locked->id : null]);
                }
            }
            $locked->save();
            $this->audit->record($locked, $active ? 'reactivate' : 'deactivate', $reason, $before, $locked->toArray(), $actor);
        }, 3);
    }

    private function archiveCover(Project $project, string $source, User $actor): void
    {
        $disk = Storage::disk('public');
        $private = 'covers/'.Str::uuid().'.'.pathinfo($source, PATHINFO_EXTENSION);
        if (! Storage::disk('commercial')->put($private, $disk->get($source))) {
            throw new \RuntimeException('No se pudo preservar la portada.');
        }
        ProjectCoverHistory::create(['project_id' => $project->id, 'user_id' => $actor->id, 'private_path' => $private, 'source_path' => $source, 'mime' => $disk->mimeType($source), 'size' => $disk->size($source), 'sha256' => hash_file('sha256', $disk->path($source)), 'created_at' => now('UTC')]);
    }

    private function retireCover(string $source, array &$relocations): void
    {
        if (Project::where('cover_image', $source)->exists()) {
            return;
        }
        // Un activo compartido de identidad sigue siendo público por ese uso.
        if (in_array($source, ['img/LOGO.png', 'img/LOGO1.png'], true)) {
            return;
        }
        abort_if(str_contains($source, '..') || str_starts_with($source, '/'), 409, 'Ruta de portada inválida.');
        $legacy = str_starts_with($source, 'img/');
        $root = realpath($legacy ? public_path('img') : Storage::disk('public')->path(''));
        $from = realpath($legacy ? public_path($source) : Storage::disk('public')->path($source));
        if (! $from) {
            return;
        }
        if (! $root || ! str_starts_with(strtolower($from), strtolower(rtrim($root, '/\\').DIRECTORY_SEPARATOR))) {
            throw new \RuntimeException('Portada fuera de su almacenamiento.');
        }
        $histories = ProjectCoverHistory::where('source_path', $source)->get();
        if ($histories->isEmpty()) {
            throw new \RuntimeException('Falta el historial privado de la portada.');
        }
        $private = Storage::disk('commercial');
        $hash = hash_file('sha256', $from);
        foreach ($histories as $history) {
            if (! $private->exists($history->private_path) || ! hash_equals($hash, hash_file('sha256', $private->path($history->private_path)))) {
                throw new \RuntimeException('La copia histórica no coincide; no se retiró la portada.');
            }
        }
        $destination = 'retired-covers/'.Str::uuid().'.'.pathinfo($source, PATHINFO_EXTENSION);
        $private->makeDirectory('retired-covers');
        $to = realpath(dirname($private->path($destination))).DIRECTORY_SEPARATOR.basename($destination);
        $privateRoot = realpath($private->path(''));
        if (! $privateRoot || ! str_starts_with(strtolower($to), strtolower(rtrim($privateRoot, '/\\').DIRECTORY_SEPARATOR))) {
            throw new \RuntimeException('Destino privado inválido.');
        }
        if (! rename($from, $to)) {
            throw new \RuntimeException('No se pudo trasladar la portada a privado.');
        }
        $relocations[] = [$from, $to];
        foreach ($histories as $history) {
            $history->update(['retired_source_path' => $destination]);
        }
    }
}

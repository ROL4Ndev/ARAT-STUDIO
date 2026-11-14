<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CatalogFilterRequest;
use App\Http\Requests\Admin\CatalogRequest;
use App\Models\AuditEvent;
use App\Models\Category;
use App\Models\Project;
use App\Models\ProjectCoverHistory;
use App\Models\TariffGroup;
use App\Models\User;
use App\Services\CatalogRegistry;
use App\Services\CatalogWorkflowService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CatalogController extends Controller
{
    private function context(Request $request, mixed $record = null): array
    {
        $kind = $request->route('catalog_kind');
        [$label,$class] = CatalogRegistry::get($kind);
        $item = $record ? $class::findOrFail($record) : new $class;
        Gate::authorize($record ? 'view' : 'create', $record ? $item : $class);

        return compact('kind', 'label', 'item');
    }

    public function index(CatalogFilterRequest $request)
    {
        $kind = $request->route('catalog_kind');
        [$label,$class] = CatalogRegistry::get($kind);
        $data = $request->validated();
        $query = $class::query();
        $field = $kind === 'projects' ? 'title' : ($kind === 'tariffs' ? 'lod_level' : 'name');
        $query->when($data['q'] ?? null, fn ($q, $s) => $q->where($field, 'like', '%'.$s.'%'));
        if (isset($data['active'])) {
            $query->where('is_active', $data['active']);
        }
        if ($kind === 'projects') {
            $query->with('projectCategory')->when($data['category'] ?? null, fn ($q, $v) => $q->where('category_id', $v));
        }
        if ($kind === 'categories') {
            $query->withCount('projects');
        }
        if ($kind === 'services') {
            $query->when($data['audience'] ?? null, fn ($q, $v) => $q->where('audience', $v));
        }
        if ($kind === 'tariffs') {
            $query->with('group')->when($data['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->when($data['lod'] ?? null, fn ($q, $v) => $q->where('lod_level', $v))->when($data['version'] ?? null, fn ($q, $v) => $q->where('version', $v));
        }
        $items = $query->orderByDesc('id')->paginate(15)->withQueryString();

        return view('admin.catalog.index', compact('kind', 'label', 'items') + ['categories' => $kind === 'projects' ? Category::orderBy('name')->get() : collect()]);
    }

    public function create(Request $request)
    {
        return view('admin.catalog.form', $this->context($request) + ['categories' => Category::where('is_active', true)->orderBy('name')->get()]);
    }

    public function show(Request $request, $record)
    {
        $c = $this->context($request, $record);
        $item = $c['item'];
        $events = AuditEvent::where('resource_type', $item->getMorphClass())->where('resource_id', $item->id)->orderByDesc('id')->paginate(15);
        $auditEvents = $events->getCollection()->map(fn (AuditEvent $event) => [
            'occurred_at' => $event->occurred_at,
            'action' => $this->auditActionLabel($event->action),
            'actor' => $event->actor_id
                ? (User::find($event->actor_id)?->name ?? 'Personal')
                : match (true) {
                    $event->actor_type === 'system' => 'Sistema',
                    str_starts_with($event->actor_type, 'client:') => 'Cliente',
                    default => 'Personal',
                },
            'reason' => $event->reason,
            'before' => $this->presentAuditSnapshot($c['kind'], $event->before),
            'after' => $this->presentAuditSnapshot($c['kind'], $event->after),
        ]);

        return view('admin.catalog.show', $c + compact('events', 'auditEvents'));
    }

    private function presentAuditSnapshot(string $kind, ?array $snapshot): array
    {
        $fields = match ($kind) {
            'categories' => [
                'name' => 'Nombre',
                'description' => 'Descripción',
                'is_active' => 'Estado',
                'lock_version' => 'Versión de edición',
                'status_reason' => 'Motivo del estado',
                'deactivated_at' => 'Fecha de desactivación',
            ],
            'projects' => [
                'title' => 'Título',
                'description' => 'Descripción',
                'category_id' => 'Categoría',
                'cover_image' => 'Portada',
                'is_active' => 'Estado',
                'lock_version' => 'Versión de edición',
                'status_reason' => 'Motivo del estado',
                'deactivated_at' => 'Fecha de desactivación',
            ],
            'services' => [
                'code' => 'Código',
                'name' => 'Nombre',
                'description' => 'Descripción',
                'audience' => 'Público',
                'display_order' => 'Orden de presentación',
                'estimation_mode' => 'Modalidad de evaluación',
                'is_active' => 'Estado',
                'lock_version' => 'Versión de edición',
                'status_reason' => 'Motivo del estado',
                'deactivated_at' => 'Fecha de desactivación',
            ],
            'tariffs' => [
                'lod_level' => 'Nivel LOD',
                'tariff_group_id' => 'Familia de tarifa',
                'version' => 'Versión de tarifa',
                'status' => 'Estado',
                'base_price_m2' => 'Tarifa base por m²',
                'lod_factor' => 'Factor LOD',
                'max_area_m2' => 'Área máxima (m²)',
                'currency' => 'Moneda',
                'version_reason' => 'Motivo de la versión',
                'is_active' => 'Publicada',
                'lock_version' => 'Versión de edición',
                'status_reason' => 'Motivo del estado',
                'deactivated_at' => 'Fecha de desactivación',
            ],
            default => [],
        };
        $presented = [];

        foreach ($fields as $key => $label) {
            if (! array_key_exists($key, $snapshot ?? [])) {
                continue;
            }

            $value = $snapshot[$key];
            if ($key === 'category_id') {
                $value = $value ? (Category::find($value)?->name ?? 'Categoría no disponible') : 'Sin categoría';
            } elseif ($key === 'tariff_group_id') {
                $value = $value ? (TariffGroup::find($value)?->lod_level ?? 'Familia no disponible') : 'Sin familia';
            } elseif ($key === 'cover_image') {
                $value = $value ? 'Configurada' : 'Sin portada';
            } elseif ($key === 'is_active') {
                $value = $value ? 'Activo' : 'Inactivo';
            } elseif ($key === 'audience') {
                $value = $value === 'estudiantes' ? 'Estudiantes' : 'General';
            } elseif ($key === 'estimation_mode') {
                $value = $value === 'manual' ? 'Evaluación manual' : 'Estimación por área y LOD';
            } elseif ($key === 'status') {
                $value = match ($value) {
                    'activa' => 'Activa',
                    'inactiva' => 'Inactiva',
                    'borrador' => 'Borrador',
                    default => $value,
                };
            } elseif ($key === 'deactivated_at' && $value) {
                $value = Carbon::parse($value)->timezone('America/Lima')->format('d/m/Y H:i:s');
            } elseif (is_bool($value)) {
                $value = $value ? 'Sí' : 'No';
            } elseif (is_array($value) || is_object($value)) {
                continue;
            }

            $presented[$label] = $value ?? '—';
        }

        return $presented;
    }

    private function auditActionLabel(string $action): string
    {
        return match ($action) {
            'catalog_create' => 'Registro creado',
            'catalog_edit' => 'Registro editado',
            'deactivate' => 'Registro desactivado',
            'reactivate' => 'Registro activado o reactivado',
            'tariff_replaced' => 'Tarifa reemplazada',
            default => 'Actualización registrada',
        };
    }

    public function edit(Request $request, $record)
    {
        $context = $this->context($request, $record);

        return view('admin.catalog.form', $context + ['categories' => Category::where(fn ($q) => $q->where('is_active', true)->orWhere('id', $context['item']->category_id))->orderBy('name')->get()]);
    }

    public function store(CatalogRequest $request, CatalogWorkflowService $service)
    {
        $kind = $request->route('catalog_kind');
        $item = $this->saveResult($request, $service, null);
        if ($item instanceof RedirectResponse) {
            return $item;
        }

        return redirect()->route('admin.'.$kind.'.show', $item->id)->with('success', 'Registro creado. Los servicios y tarifas nuevos quedan como borradores hasta activarlos.');
    }

    public function update(CatalogRequest $request, $record, CatalogWorkflowService $service)
    {
        $kind = $request->route('catalog_kind');
        [, $class] = CatalogRegistry::get($kind);
        $item = $this->saveResult($request, $service, $class::findOrFail($record));
        if ($item instanceof RedirectResponse) {
            return $item;
        }

        return redirect()->route('admin.'.$kind.'.show', $item->id)->with('success', 'Cambio registrado con auditoría.');
    }

    public function status(CatalogRequest $request, $record, CatalogWorkflowService $service)
    {
        $kind = $request->route('catalog_kind');
        [, $class] = CatalogRegistry::get($kind);
        $service->status($class::findOrFail($record), $request->boolean('active'), (int) $request->validated('lock_version'), $request->validated('reason'), $request->user(), $request->filled('expected_active_id') ? (int) $request->validated('expected_active_id') : null, $kind === 'tariffs');

        return redirect()->route('admin.'.$kind.'.show', $record)->with('success', 'Estado actualizado con motivo e historial.');
    }

    private function saveResult(CatalogRequest $request, CatalogWorkflowService $service, ?Model $record)
    {
        try {
            return $service->save($request->route('catalog_kind'), $record, $request->validated(), $request->file('cover_image'), $request->user());
        } catch (ValidationException|AuthorizationException|HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput($request->safe()->except('cover_image'))->withErrors(['save' => 'No se confirmó el guardado. Revisa los datos o la portada y vuelve a intentar. Los archivos anteriores se conservan.']);
        }
    }

    public function cover(ProjectCoverHistory $history)
    {
        Gate::authorize('view', Project::findOrFail($history->project_id));
        $disk = Storage::disk('commercial');
        abort_unless($disk->exists($history->private_path), 404);

        return $disk->download($history->private_path, 'portada-historica.'.pathinfo($history->private_path, PATHINFO_EXTENSION), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}

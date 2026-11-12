<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CatalogFilterRequest;
use App\Http\Requests\Admin\CatalogRequest;
use App\Models\AuditEvent;
use App\Models\Category;
use App\Models\Project;
use App\Models\ProjectCoverHistory;
use App\Services\CatalogRegistry;
use App\Services\CatalogWorkflowService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return view('admin.catalog.show', $c + compact('events'));
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

        return redirect()->route('admin.'.$kind.'.show', $item->id)->with('success', 'Cambio registrado con auditoría. Si la tarifa ya estaba publicada, se creó una versión borrador.');
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
        } catch (ValidationException|\Illuminate\Auth\Access\AuthorizationException|HttpException $e) {
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

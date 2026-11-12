@extends('admin.layout')

@section('title', ($item->title ?? $item->name ?? $item->lod_level).' | ARAT STUDIO')
@section('admin-title', $label.' — '.($item->title ?? $item->name ?? $item->lod_level))

@section('admin-content')
    @include('shared.errors')
    @php($state = $item->status ?? ($item->is_active ? 'activo' : ($kind === 'projects' ? 'borrador' : 'inactivo')))

    <article class="workspace-card">
        <h2>Consulta del registro</h2>
        <dl class="admin-project-detail__data">
            <dt>Estado</dt>
            <dd>@include('shared.status-label', ['value' => $state])</dd>
            <dt>Versión de edición</dt>
            <dd>{{ $item->lock_version }}</dd>
            @if ($item->description)
                <dt>Descripción</dt>
                <dd class="text-break" style="white-space: pre-wrap">{{ $item->description }}</dd>
            @endif
        </dl>

        @if ($kind === 'tariffs')
            <h3>Tarifa publicada o borrador</h3>
            <p>Versión {{ $item->version }} · PEN {{ $item->base_price_m2 }} por m² × {{ $item->lod_factor }} · límite {{ $item->max_area_m2 }} m².</p>
            <p>Versión vigente en su familia: <strong>{{ $item->group->active_tariff_id === $item->id ? 'Sí' : 'No' }}</strong></p>
            <p>Editar una tarifa publicada crea una nueva versión en borrador; no cambia el importe de la versión vigente.</p>
        @endif

        @if ($kind === 'services')
            <p>Código único: {{ $item->code }} · Público: {{ $item->audience === 'estudiantes' ? 'Estudiantes' : 'General' }} ·
                {{ $item->estimation_mode === 'manual' ? 'Evaluación manual' : 'Estimación por área y LOD' }}
            </p>
        @endif

        @if ($kind === 'categories')
            <h3>Proyectos asociados y dependencias</h3>
            <p>La desactivación queda impedida mientras haya proyectos activos en esta categoría.</p>
            <ul>
                @forelse ($item->projects()->get() as $project)
                    <li>
                        <a href="{{ route('admin.projects.show', $project->id) }}">{{ $project->title }}</a>
                        · @include('shared.status-label', ['value' => $project->is_active ? 'activo' : 'inactivo'])
                        @if ($project->is_active)
                            <strong>Esta dependencia activa impide desactivar la categoría.</strong>
                        @endif
                    </li>
                @empty
                    <li>Sin proyectos asociados.</li>
                @endforelse
            </ul>
        @endif

        @if ($kind === 'projects')
            <img class="admin-project-cover" src="{{ $item->cover_image_url }}" alt="Portada de {{ $item->title }}" width="800" height="600">
            <h3>Portadas conservadas</h3>
            <ul>
                @forelse ($item->coverHistories()->with('user')->latest('id')->get() as $cover)
                    <li>
                        <a href="{{ route('admin.covers.download', $cover->id) }}">Descargar portada histórica</a>
                        · {{ $cover->created_at->timezone('America/Lima')->format('d/m/Y H:i:s') }} (Lima)
                        · {{ $cover->user?->name ?? 'Importación heredada' }}
                        · {{ $cover->mime }} · {{ $cover->size }} bytes
                    </li>
                @empty
                    <li>No hay portadas históricas.</li>
                @endforelse
            </ul>
        @endif

        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-dark admin-button" href="{{ route('admin.'.$kind.'.edit', $item->id) }}">
                Editar{{ $kind === 'tariffs' && $item->status !== 'borrador' ? ' como nueva versión' : '' }}
            </a>
            <a class="btn btn-outline-dark admin-button" href="{{ route('admin.'.$kind.'.index') }}">Volver al listado de {{ mb_strtolower($label) }}</a>
        </div>
    </article>

    <section class="workspace-card mt-4" aria-labelledby="status-change-title">
        <h2 id="status-change-title">Cambiar estado</h2>
        <p>
            {{ $item->is_active ? 'La acción retirará este registro de nuevas operaciones públicas.' : 'La acción habilitará el mismo registro sin eliminar su historial.' }}
            @if ($kind === 'tariffs' && ! $item->is_active)
                Se actualizará la tarifa vigente de esta familia.
            @endif
        </p>
        <form
            method="POST"
            action="{{ route('admin.'.$kind.'.status', $item->id) }}"
            data-confirm="{{ $item->is_active ? '¿Confirmas desactivar este registro? El historial se conservará.' : '¿Confirmas activar o reactivar este registro?' }}"
        >
            @csrf
            @if ($kind === 'tariffs')
                <input type="hidden" name="expected_active_id" value="{{ $item->group->active_tariff_id }}">
            @endif
            <input type="hidden" name="lock_version" value="{{ $item->lock_version }}">
            <input type="hidden" name="active" value="{{ $item->is_active ? 0 : 1 }}">
            <label for="reason" class="form-label">Motivo para {{ $item->is_active ? 'desactivar' : 'activar o reactivar' }} <span aria-hidden="true">*</span></label>
            <textarea class="form-control @error('reason') is-invalid @enderror" name="reason" id="reason" required minlength="5" maxlength="2000" @error('reason') aria-invalid="true" aria-describedby="reason-error" @enderror>{{ old('reason') }}</textarea>
            @error('reason')
                <p class="form-feedback" id="reason-error" role="alert">{{ $message }}</p>
            @enderror
            <button class="btn btn-dark admin-button mt-3" type="submit" data-loading-label="Actualizando estado…">
                {{ $item->is_active ? 'Desactivar registro' : 'Activar o reactivar registro' }}
            </button>
        </form>
    </section>

    <section class="workspace-card mt-4" aria-labelledby="audit-title">
        <h2 id="audit-title">Auditoría</h2>
        <ol>
            @forelse ($events as $event)
                <li class="mb-3">
                    {{ $event->occurred_at->timezone('America/Lima')->format('d/m/Y H:i:s') }} (Lima)
                    · {{ $event->action }} · actor {{ $event->actor_type }} {{ $event->actor_id ?? '' }}
                    · {{ $event->reason }}
                    <details>
                        <summary>Consultar datos anteriores y posteriores</summary>
                        <pre class="text-wrap">{{ json_encode(['antes' => $event->before, 'despues' => $event->after], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                    </details>
                </li>
            @empty
                <li>No hay cambios posteriores a la importación.</li>
            @endforelse
        </ol>
        {{ $events->links() }}
    </section>
@endsection

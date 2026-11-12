@extends('admin.layout')

@section('title', $label.' | ARAT STUDIO')
@section('admin-title', $label)

@section('admin-content')
    @include('shared.errors')

    <div class="admin-toolbar">
        <p>Consulta, filtra y administra los registros de {{ mb_strtolower($label) }}.</p>
        <a class="btn btn-dark admin-button" href="{{ route('admin.'.$kind.'.create') }}">Crear {{ mb_strtolower(rtrim($label, 's')) }}</a>
    </div>

    <form method="GET" action="{{ route('admin.'.$kind.'.index') }}" class="admin-search" aria-label="Filtros de {{ mb_strtolower($label) }}">
        <label for="q" class="form-label">Buscar</label>
        <input class="form-control" id="q" name="q" type="search" value="{{ request('q') }}" maxlength="150">

        <label for="active" class="form-label">Estado de publicación</label>
        <select class="form-select" id="active" name="active">
            <option value="">Todos</option>
            <option value="1" @selected(request('active') === '1')>Activos</option>
            <option value="0" @selected(request('active') === '0')>Inactivos y borradores</option>
        </select>

        @if ($kind === 'projects')
            <label for="category" class="form-label">Categoría</label>
            <select id="category" name="category" class="form-select">
                <option value="">Todas</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) request('category') === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        @endif

        @if ($kind === 'tariffs')
            <label for="lod" class="form-label">LOD</label>
            <select id="lod" name="lod" class="form-select">
                <option value="">Todos</option>
                @foreach (['LOD_200', 'LOD_300', 'LOD_400'] as $lod)
                    <option @selected(request('lod') === $lod)>{{ $lod }}</option>
                @endforeach
            </select>
            <label for="tariff_status" class="form-label">Estado de tarifa</label>
            <select id="tariff_status" name="status" class="form-select">
                <option value="">Todos</option>
                @foreach (['borrador', 'activa', 'inactiva'] as $state)
                    <option @selected(request('status') === $state)>{{ $state }}</option>
                @endforeach
            </select>
            <label for="version" class="form-label">Versión</label>
            <input id="version" name="version" type="number" min="1" class="form-control" value="{{ request('version') }}">
        @endif

        @if ($kind === 'services')
            <label for="audience" class="form-label">Público</label>
            <select id="audience" name="audience" class="form-select">
                <option value="">Todos</option>
                @foreach (['general', 'estudiantes'] as $audience)
                    <option @selected(request('audience') === $audience)>{{ $audience }}</option>
                @endforeach
            </select>
        @endif

        <button class="btn btn-dark admin-button" type="submit">Aplicar filtros</button>
        <a class="btn btn-outline-dark admin-button" href="{{ route('admin.'.$kind.'.index') }}">Limpiar filtros</a>
    </form>

    <div class="table-responsive" role="region" aria-label="Resultados de {{ mb_strtolower($label) }}" tabindex="0">
        <table class="table admin-table align-middle">
            <thead>
                <tr>
                    <th scope="col">Registro</th>
                    <th scope="col">Estado</th>
                    <th scope="col">Información</th>
                    <th scope="col"><span class="visually-hidden">Acciones</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    @php($state = $item->status ?? ($item->is_active ? 'activo' : ($kind === 'projects' ? 'borrador' : 'inactivo')))
                    <tr>
                        <th scope="row">{{ $item->title ?? $item->name ?? $item->lod_level }}</th>
                        <td>@include('shared.status-label', ['value' => $state])</td>
                        <td>
                            @if ($kind === 'tariffs')
                                Versión {{ $item->version }} · PEN {{ $item->base_price_m2 }} × {{ $item->lod_factor }} · límite {{ $item->max_area_m2 }} m²
                            @elseif ($kind === 'projects')
                                {{ $item->projectCategory?->name ?? 'Sin categoría' }}
                            @elseif ($kind === 'services')
                                {{ $item->code }} · {{ $item->estimation_mode === 'manual' ? 'evaluación manual' : 'área y LOD' }}
                            @else
                                {{ $item->slug }} · {{ $item->projects_count }} proyectos asociados
                            @endif
                        </td>
                        <td class="admin-table__actions">
                            <a class="btn btn-outline-dark btn-sm" href="{{ route('admin.'.$kind.'.show', $item->id) }}">Consultar {{ mb_strtolower(rtrim($label, 's')) }}</a>
                            <a class="btn btn-outline-dark btn-sm" href="{{ route('admin.'.$kind.'.edit', $item->id) }}">Editar {{ mb_strtolower(rtrim($label, 's')) }}</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">No hay registros para estos filtros. Puedes limpiar los filtros o crear un registro.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $items->links() }}
@endsection

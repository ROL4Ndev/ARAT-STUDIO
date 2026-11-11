@extends('admin.layout')

@section('title', 'Proyectos | ARAT STUDIO')
@section('admin-title', 'Proyectos')

@section('admin-content')
    <div class="admin-toolbar">
        <p>Consulta, crea y actualiza los proyectos del portafolio.</p>
        <a class="btn btn-dark admin-button" href="{{ route('admin.projects.create') }}">Nuevo proyecto</a>
    </div>

    <form method="GET" action="{{ route('admin.projects.index') }}" class="admin-search">
        <label for="q" class="form-label">Buscar por título</label>
        <input id="q" name="q" type="search" class="form-control" value="{{ $q = request('q') }}" maxlength="180">
        <button class="btn btn-outline-dark admin-button" type="submit">Buscar</button>
        @if ($q)
            <a href="{{ route('admin.projects.index') }}">Limpiar</a>
        @endif
    </form>

    <div class="table-responsive">
        <table class="table admin-table align-middle">
            <thead>
                <tr>
                    <th scope="col">Proyecto</th>
                    <th scope="col">Categoría</th>
                    <th scope="col">Estado</th>
                    <th scope="col"><span class="visually-hidden">Acciones</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($projects as $project)
                    <tr>
                        <td>{{ $project->title }}</td>
                        <td>{{ $project->projectCategory?->name ?? 'Sin categoría' }}</td>
                        <td>{{ $project->is_active ? 'Activo' : 'Inactivo' }}</td>
                        <td class="admin-table__actions">
                            <a class="btn btn-outline-dark btn-sm" href="{{ route('admin.projects.show', $project) }}">Ver</a>
                            <a class="btn btn-outline-dark btn-sm" href="{{ route('admin.projects.edit', $project) }}">Editar</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4">No se encontraron proyectos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $projects->links() }}
@endsection

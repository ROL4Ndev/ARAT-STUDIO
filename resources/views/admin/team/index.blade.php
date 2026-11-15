@extends('admin.layout')
@section('title', 'Equipo · Gestores | ARAT STUDIO')
@section('admin-title', 'Equipo de gestión')
@section('admin-content')
    @include('shared.errors')
    <div class="admin-toolbar">
        <p>Organiza a los responsables que acompañan cada solicitud.</p>
        <a class="btn btn-dark" href="{{ route('admin.team.create') }}">Crear gestor</a>
    </div>
    <form class="admin-search" method="GET" aria-label="Buscar gestores">
        <div>
            <label class="form-label" for="q">Nombre o correo</label>
            <input class="form-control" id="q" name="q" type="search" value="{{ request('q') }}"
                maxlength="150">
        </div>
        <div>
            <label class="form-label" for="active">Estado</label>
            <select class="form-select" id="active" name="active">
                <option value="">Todos</option>
                <option value="1" @selected(request('active') === '1')>Activos</option>
                <option value="0" @selected(request('active') === '0')>Inactivos</option>
            </select>
        </div>
        <button class="btn btn-outline-dark" type="submit">Buscar</button>
        <a href="{{ route('admin.team.index') }}">Limpiar filtros</a>
    </form>
    <div class="table-responsive" role="region" aria-label="Gestores" tabindex="0">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th scope="col">Gestor</th>
                    <th scope="col">Estado</th>
                    <th scope="col">Expedientes abiertos</th>
                    <th scope="col">Acción</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($managers as $manager)
                    <tr>
                        <th scope="row">{{ $manager->name }}<br>
                            <span class="text-muted">{{ $manager->email }}</span>
                        </th>
                        <td>
                            @include('shared.status-label', [
                                'value' => $manager->is_active ? 'activo' : 'inactivo',
                            ])</td>
                        <td>{{ $manager->open_requests_count }}</td>
                        <td>
                            <a href="{{ route('admin.team.show', $manager) }}">Consultar gestor</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">
                            <div class="empty-state">No hay gestores con estos filtros. Puedes limpiar la búsqueda o crear
                                la primera cuenta.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $managers->links() }}
@endsection

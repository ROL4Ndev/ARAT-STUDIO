@extends('admin.layout')

@section('title', 'Categorías | ARAT STUDIO')
@section('admin-title', 'Categorías')

@section('admin-content')
    <div class="admin-toolbar">
        <p>Organiza los proyectos del portafolio por categoría.</p>
        <a class="btn btn-dark admin-button" href="{{ route('admin.categories.create') }}">Nueva categoría</a>
    </div>

    <div class="table-responsive">
        <table class="table admin-table align-middle">
            <thead>
                <tr>
                    <th scope="col">Nombre</th>
                    <th scope="col">Slug</th>
                    <th scope="col">Proyectos</th>
                    <th scope="col"><span class="visually-hidden">Acciones</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr>
                        <td>{{ $category->name }}</td>
                        <td>{{ $category->slug }}</td>
                        <td>{{ $category->projects_count }}</td>
                        <td class="admin-table__actions">
                            <a class="btn btn-outline-dark btn-sm" href="{{ route('admin.categories.edit', $category) }}">Editar</a>
                            <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button
                                    class="btn btn-outline-danger btn-sm"
                                    type="submit"
                                    @disabled($category->projects_count > 0)
                                    aria-label="Eliminar categoría {{ $category->name }}"
                                >
                                    Eliminar
                                </button>
                            </form>
                            @if ($category->projects_count > 0)
                                <span class="admin-table__hint">Tiene proyectos asociados</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4">Aún no hay categorías.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $categories->links() }}
@endsection

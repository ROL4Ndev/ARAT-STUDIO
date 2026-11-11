@extends('admin.layout')

@section('title', $project->title.' | ARAT STUDIO')
@section('admin-title', 'Consultar proyecto')

@section('admin-content')
    <article class="admin-project-detail">
        <img class="admin-project-cover admin-project-cover--large" src="{{ $project->cover_image_url }}" alt="Portada de {{ $project->title }}">
        <dl class="admin-project-detail__data">
            <dt>Título</dt>
            <dd>{{ $project->title }}</dd>
            <dt>Categoría</dt>
            <dd>{{ $project->projectCategory?->name ?? 'Sin categoría' }}</dd>
            <dt>Descripción</dt>
            <dd>{{ $project->description }}</dd>
            <dt>Slug</dt>
            <dd>{{ $project->slug }}</dd>
            <dt>Estado</dt>
            <dd>{{ $project->is_active ? 'Activo' : 'Inactivo' }}</dd>
        </dl>
        <a class="btn btn-dark admin-button" href="{{ route('admin.projects.edit', $project) }}">Editar proyecto</a>
        <a class="btn btn-outline-dark admin-button" href="{{ route('admin.projects.index') }}">Volver al listado</a>
    </article>
@endsection

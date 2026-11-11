@extends('admin.layout')

@section('title', 'Editar categoría | ARAT STUDIO')
@section('admin-title', 'Editar categoría')

@section('admin-content')
    <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="admin-form">
        @csrf
        @method('PUT')
        @include('admin.categories._form', ['buttonLabel' => 'Guardar cambios'])
    </form>
@endsection

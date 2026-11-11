@extends('admin.layout')

@section('title', 'Editar proyecto | ARAT STUDIO')
@section('admin-title', 'Editar proyecto')

@section('admin-content')
    <form method="POST" action="{{ route('admin.projects.update', $project) }}" enctype="multipart/form-data" class="admin-form">
        @csrf
        @method('PUT')
        @include('admin.projects._form', ['buttonLabel' => 'Guardar cambios'])
    </form>
@endsection

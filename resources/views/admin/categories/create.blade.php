@extends('admin.layout')

@section('title', 'Nueva categoría | ARAT STUDIO')
@section('admin-title', 'Nueva categoría')

@section('admin-content')
    <form method="POST" action="{{ route('admin.categories.store') }}" class="admin-form">
        @csrf
        @include('admin.categories._form', ['buttonLabel' => 'Crear categoría'])
    </form>
@endsection

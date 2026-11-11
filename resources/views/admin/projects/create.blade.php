@extends('admin.layout')

@section('title', 'Nuevo proyecto | ARAT STUDIO')
@section('admin-title', 'Nuevo proyecto')

@section('admin-content')
    <form method="POST" action="{{ route('admin.projects.store') }}" enctype="multipart/form-data" class="admin-form">
        @csrf
        @include('admin.projects._form', ['buttonLabel' => 'Crear proyecto'])
    </form>
@endsection

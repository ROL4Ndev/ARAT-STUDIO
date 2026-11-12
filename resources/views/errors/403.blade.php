@extends('layouts.app')
@section('title', 'Acceso no autorizado | ARAT STUDIO')
@section('content')
<section class="workspace-view"><div class="workspace-content"><div class="container"><div class="workspace-card"><h1>Acceso no autorizado</h1><p>{{ $exception->getMessage() ?: 'Tu sesión no tiene permiso para este recurso.' }}</p><a class="btn btn-dark" href="{{ route('home') }}">Volver al inicio</a> <a href="{{ route('client.access') }}">Solicitar acceso a mi expediente</a></div></div></div></section>
@endsection

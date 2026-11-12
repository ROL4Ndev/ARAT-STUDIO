@extends('layouts.app')
@section('title', 'Revisa el estado actual | ARAT STUDIO')
@section('content')
<section class="workspace-view"><div class="workspace-content"><div class="container"><div class="workspace-card"><h1>El registro cambió</h1><p>{{ $exception->getMessage() ?: 'Esta acción entra en conflicto con el estado actual. Recarga el expediente antes de continuar.' }}</p><a class="btn btn-dark" href="{{ url()->previous() }}">Volver y revisar</a></div></div></div></section>
@endsection

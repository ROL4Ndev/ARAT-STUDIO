@extends('layouts.app')
@section('title', 'Revisa el estado actual | ARAT STUDIO')
@section('content')
<section class="workspace-view"><div class="workspace-content"><div class="container"><div class="workspace-card" role="alert"><h1>El registro cambió</h1><p>{{ $exception->getMessage() ?: 'Esta acción entra en conflicto con el estado actual.' }}</p><p>No se sobrescribió el cambio concurrente. Recarga el expediente para consultar el estado actualizado; no vuelvas a enviar el mismo formulario sin revisarlo.</p><a class="btn btn-dark" href="{{ url()->previous() }}">Volver al expediente y revisar</a></div></div></div></section>
@endsection

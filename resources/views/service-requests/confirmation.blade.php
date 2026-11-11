@extends('layouts.app')
@section('content')
<section class="workspace-view"><div class="workspace-hero"><div class="container"><h1>Solicitud registrada</h1><p>Tu código es <strong>{{ $code }}</strong>.</p><p>El equipo evaluará tu proyecto y se comunicará contigo. Conserva este código; no necesitas volver a enviar la solicitud.</p><a href="{{ route('home') }}" class="btn btn-dark">Volver al inicio</a></div></div></section>
@endsection

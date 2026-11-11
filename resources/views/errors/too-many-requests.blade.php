@extends('layouts.app')
@section('content')<section class="workspace-view"><div class="workspace-hero"><div class="container"><h1>Espera un momento</h1><p>Se recibieron demasiados intentos desde tu conexión. Espera un minuto antes de volver a enviar el formulario.</p><a href="{{ route('service-requests.create') }}">Volver a Solicitar proyecto</a></div></div></section>@endsection

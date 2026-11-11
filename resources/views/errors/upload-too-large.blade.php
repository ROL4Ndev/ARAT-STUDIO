@extends('layouts.app')
@section('content')<section class="workspace-view"><div class="workspace-hero"><div class="container"><h1>No se pudo recibir el archivo</h1><p>El envío excede el límite del servidor. Utiliza un enlace externo en lugar del adjunto.</p><a href="{{ route('service-requests.create') }}">Volver a Solicitar proyecto</a></div></div></section>@endsection

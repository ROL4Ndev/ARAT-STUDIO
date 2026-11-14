@extends('layouts.app')
@section('title', 'Solicitud registrada | ARAT STUDIO')
@section('content')
<section class="workspace-view" aria-labelledby="confirmation-title">
    <header class="workspace-hero"><div class="container">
        <p class="section-label">El primer paso está completo</p>
        <h1 id="confirmation-title">Solicitud registrada</h1>
        <p>Tu código es <strong class="text-break">{{ $code }}</strong>.</p>
        <p>El equipo evaluará tu proyecto y se comunicará contigo. Conserva este código; no necesitas volver a enviar la solicitud.</p>
    </div></header>
    <div class="workspace-content"><div class="container">
        <section class="workspace-card" aria-labelledby="confirmation-next">
            <h2 id="confirmation-next">¿Qué sigue ahora?</h2>
            <ol class="history-timeline">
                <li><strong>Revisamos tu información.</strong><p>Un responsable evaluará el alcance y te pedirá más detalles si los necesita.</p></li>
                <li><strong>Preparamos una propuesta.</strong><p>Después de la aprobación técnica recibirás una cotización oficial. La estimación inicial es referencial.</p></li>
                <li><strong>Lo consultas en tu expediente.</strong><p>Utiliza tu código y el correo registrado para solicitar un enlace de acceso temporal.</p></li>
            </ol>
            <a href="{{ route('client.access') }}" class="btn btn-dark">Acceder a mi expediente</a>
            <a href="{{ route('home') }}" class="btn btn-outline-dark">Volver al inicio</a>
        </section>
    </div></div>
</section>
@endsection

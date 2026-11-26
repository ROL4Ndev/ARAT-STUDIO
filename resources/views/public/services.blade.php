@extends('layouts.app')

@section('title', 'Servicios | ARAT STUDIO')

@section('content')
    <section class="section-block section-block--gray public-info-page" aria-labelledby="services-title">
        <div class="container">
            <header class="section-header mb-5" data-reveal="title">
                <span class="section-label">Qué ofrecemos</span>
                <h1 id="services-title" class="section-title mt-2">Del diseño a la visualización.</h1>
            </header>
            @include('public.partials.service-stories')
        </div>
    </section>
    <section class="section-block" aria-labelledby="process-title">
        <div class="container">
            <p class="section-label">Cómo trabajamos</p>
            <h2 id="process-title" class="section-title">De tu idea a una propuesta clara.</h2>
            <ol class="process-steps">
                <li><h3>Cuéntanos el proyecto</h3><p>Comparte tus necesidades, el área y las referencias disponibles en una solicitud.</p></li>
                <li><h3>Evaluamos el alcance</h3><p>El equipo revisa la información y te pide una corrección si hace falta precisar algún detalle.</p></li>
                <li><h3>Revisa la propuesta</h3><p>Consulta la cotización oficial en tu expediente y registra tu decisión sobre la versión vigente.</p></li>
            </ol>
            <a class="btn btn-dark" href="{{ route('service-requests.create') }}">Solicitar una evaluación</a>
            <div class="service-faq mt-5" aria-labelledby="faq-title">
                <h2 id="faq-title">Antes de empezar</h2>
                <details><summary>¿La estimación es una cotización oficial?</summary><p>No. La estimación usa el área, el nivel de detalle y las tarifas disponibles. El equipo debe evaluar el alcance antes de emitir una propuesta oficial con su PDF y vigencia.</p></details>
                <details><summary>¿Cómo consulto el avance o corrijo mi solicitud?</summary><p>En «Consultar mi expediente», ingresa tu código y el correo registrado. Desde el enlace temporal podrás ver el avance, responder observaciones y consultar tus documentos.</p></details>
                <details><summary>¿Puedo consultar versiones anteriores?</summary><p>El expediente conserva las propuestas anteriores. Solo puedes responder a la versión vigente que no haya vencido.</p></details>
            </div>
        </div>
    </section>
@endsection

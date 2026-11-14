@extends('layouts.app')

@section('title', 'Contacto | ARAT STUDIO')

@section('content')
    <section class="section-block section-block--dark home-contact public-info-page" aria-labelledby="contact-title">
        <div class="container">
            <div class="row gx-4 gy-5 align-items-start">
                <div class="col-lg-6">
                    <span class="section-label">Hablemos</span>
                    <h1 id="contact-title" class="section-title section-title--light mt-3">¿Tienes un proyecto<br>en mente?</h1>
                    <p class="section-body mt-4">Escríbenos o registra tu solicitud para que podamos evaluar el alcance de tu proyecto.</p>
                    <a href="{{ route('service-requests.create') }}" class="btn btn-light home-button">Solicitar proyecto <span aria-hidden="true">→</span></a>
                </div>
                <div class="col-lg-6">
                    <address class="home-contact-details">
                        <a href="mailto:{{ config('studio.email') }}"><span>Correo electrónico</span><strong>{{ config('studio.email') }}</strong></a>
                        <a href="tel:{{ config('studio.phone_link') }}"><span>Teléfono</span><strong>{{ config('studio.phone') }}</strong></a>
                        <a href="{{ config('studio.whatsapp') }}" target="_blank" rel="noopener noreferrer"><span>WhatsApp · abre otra pestaña</span><strong>Conversa con el estudio <span aria-hidden="true">↗</span></strong></a>
                        <p class="mb-0 mt-4">Lima, Perú</p>
                    </address>
                </div>
            </div>
        </div>
    </section>
@endsection

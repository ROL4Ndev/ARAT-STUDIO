@extends('layouts.app')

@section('title', 'ARAT STUDIO | Arquitectura y Diseño en Lima')

@section('content')
    <section id="inicio" class="home-hero" aria-labelledby="home-title">
        <div class="home-hero__grid">
            <div class="home-hero__copy">
                <p class="hero__eyebrow">Estudio de arquitectura · Lima, Perú</p>
                <h1 id="home-title">Espacios con propósito.</h1>
                <p>Arquitectura · Diseño · Visualización 3D</p>
                <p>Transformamos tus ideas en espacios que conectan la forma, la luz y tu manera de vivir.</p>
                <div class="home-hero-actions">
                    <a href="{{ route('projects.index') }}" class="btn btn-dark">Explorar portafolio <span
                            aria-hidden="true">→</span></a>
                    <a href="{{ route('service-requests.create') }}" class="home-intro-link">Hablemos de tu proyecto</a>
                </div>
            </div>
            @include('public.partials.studio-film', [
                'hero' => true,
                'id' => 'home-interior-video',
                'file' => 'inicio-charlotte-may',
                'label' => 'interior referencial',
                'posterAlt' => 'Interior referencial con mesa de comedor, luminarias y elementos de madera',
            ])

        </div>
    </section>

    <section id="sobre" class="section-block" aria-labelledby="about-title">
        <div class="container">
            @include('public.partials.studio-intro')
        </div>
    </section>

    <section id="servicios" class="section-block section-block--gray" aria-labelledby="services-title">
        <div class="container">
            <header class="section-header mb-5">
                <span class="section-label">Qué ofrecemos</span>
                <h2 id="services-title" class="section-title mt-2">Del diseño a la visualización.</h2>
            </header>
            <div class="service-overview">
                @foreach ($services->flatten(1) as $service)
                    <a class="service-overview__item" href="{{ route('services') }}#servicio-{{ $service['id'] }}">
                        @if ($service['image'])
                            <img src="{{ asset($service['image']) }}" alt="" width="160" height="120" loading="lazy">
                        @endif
                        <span><strong>{{ $service['name'] }}</strong><span>{{ $service['description'] }}</span></span>
                        <span class="service-overview__link">Ver servicio</span>
                    </a>
                @endforeach
            </div>
            <a class="btn btn-dark home-button" href="{{ route('service-requests.create') }}">Cuéntanos qué necesitas <span
                    aria-hidden="true">→</span></a>
        </div>
    </section>

    <section id="equipo" class="section-block" aria-labelledby="team-title">
        <div class="container">
            <header class="section-header mb-5">
                <span class="section-label">Quiénes somos</span>
                <h2 id="team-title" class="section-title mt-2">El equipo detrás del proyecto.</h2>
            </header>
            @include('public.partials.team', ['compact' => true])
            <a class="home-intro-link mt-4 d-inline-block" href="{{ route('about') }}#equipo">Conoce nuestra experiencia y especialidades</a>
        </div>
    </section>

    <section id="proyectos" class="section-block section-block--gray" aria-labelledby="projects-title">
        <div class="container">
            <div class="section-header mb-5">
                <span class="section-label">Nuestro trabajo</span>
                <h2 id="projects-title" class="section-title mt-2">
                    Proyectos destacados
                </h2>
            </div>

            <div class="row g-4">
                @forelse ($projects as $project)
                    <div class="col-md-6 col-lg-4">
                        @include('layouts.components.project-card', [
                            'project' => $project,
                            'headingLevel' => 3,
                        ])
                    </div>
                @empty
                    <div class="col-12">
                        <p class="text-center">Explora nuestros proyectos de arquitectura, interiores y visualización.</p>
                    </div>
                @endforelse
            </div>

            <div class="text-center mt-5">
                <a href="{{ route('projects.index') }}" class="btn btn-outline-dark">
                    Ver todo el portafolio
                </a>
            </div>
        </div>
    </section>

    <section id="contacto" class="section-block section-block--dark home-contact" aria-labelledby="contact-title">
        <div class="container">
            <div class="row gx-4 gy-5 align-items-start">
                <div class="col-lg-6">
                    <span class="section-label">Hablemos</span>
                    <h2 id="contact-title" class="section-title section-title--light mt-3">¿Tienes un proyecto<br>en mente?
                    </h2>
                    <p class="section-body mt-4">Escríbenos o registra tu solicitud para que podamos evaluar el alcance de
                        tu proyecto.</p>
                    <a href="{{ route('service-requests.create') }}" class="btn btn-light home-button">Solicitar proyecto
                        <span aria-hidden="true">→</span></a>
                </div>
                <div class="col-lg-6">
                    <address class="home-contact-details">
                        <a href="mailto:{{ config('studio.email') }}"><span>Correo
                                electrónico</span><strong>{{ config('studio.email') }}</strong></a>
                        <a
                            href="tel:{{ config('studio.phone_link') }}"><span>Teléfono</span><strong>{{ config('studio.phone') }}</strong></a>
                        <a href="{{ config('studio.whatsapp') }}" target="_blank" rel="noopener noreferrer"><span>WhatsApp ·
                                abre otra pestaña</span><strong>Conversa con el estudio <span
                                    aria-hidden="true">↗</span></strong></a>
                        <p class="mb-0 mt-4">Lima, Perú</p>
                    </address>
                </div>
            </div>
        </div>
    </section>
@endsection

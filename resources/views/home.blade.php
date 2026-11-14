@extends('layouts.app')

@section('title', 'ARAT STUDIO | Arquitectura y Diseño en Lima')

@section('content')
    <section
        id="inicio"
        class="hero d-flex align-items-center justify-content-center"
        aria-labelledby="home-title"
    >
        @if ($projects->isNotEmpty())
            <div class="home-hero-photo" aria-hidden="true">
                <img src="{{ $projects->first()->cover_image_url }}" alt="" fetchpriority="high" width="1600" height="1000">
            </div>
        @endif
        <div class="hero__bg-logo" aria-hidden="true">
            <img src="{{ asset('img/LOGO.png') }}" alt="">
        </div>

        <div class="hero__overlay" aria-hidden="true"></div>

        <div class="container text-center position-relative home-hero-content">
            <p class="hero__eyebrow">Lima, Perú</p>

            <div class="hero__logo-wrap">
                <img
                    src="{{ asset('img/LOGO1.png') }}"
                    alt="ARAT STUDIO"
                    class="hero__logo"
                >
            </div>

            <h1 id="home-title" class="hero__subtitle">
                Arquitectura · Diseño · Visualización 3D
            </h1>

            <div class="home-hero-actions mt-4">
                <a href="{{ route('projects.index') }}" class="btn btn-light btn-hero">Explorar portafolio</a>
                <a href="{{ route('service-requests.create') }}" class="btn btn-outline-light btn-hero">Solicitar proyecto</a>
            </div>
            <a href="#sobre" class="home-intro-link">Conocer el estudio <span aria-hidden="true">↓</span></a>
        </div>
    </section>

    <section
        id="sobre"
        class="section-block"
        aria-labelledby="about-title"
    >
        <div class="container">
            <div class="section-header text-center mb-5">
                <span class="section-label">Nuestro enfoque</span>
                <h2 id="about-title" class="section-title mt-2">
                    Sobre el estudio
                </h2>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h3 class="studio-info__title mb-4">
                        Diseño con propósito.<br>
                        Arquitectura con precisión.
                    </h3>

                    <p class="section-body">
                        ARAT STUDIO es un estudio de arquitectura y diseño
                        con sede en Lima, Perú, enfocado en proyectos
                        residenciales, comerciales e institucionales.
                    </p>

                    <p class="section-body">
                        Nuestra práctica integra arquitectura, diseño
                        de interiores, modelado BIM y renderización
                        fotorrealista, acompañando cada proyecto desde
                        el concepto hasta la comunicación visual.
                    </p>
                    <a href="#equipo" class="project-detail-link">Conoce al equipo <span aria-hidden="true">→</span></a>
                </div>
            </div>
        </div>
    </section>

    <section id="servicios" class="section-block section-block--gray" aria-labelledby="services-title">
        <div class="container">
            <header class="section-header mb-5">
                <span class="section-label">Qué ofrecemos</span>
                <h2 id="services-title" class="section-title mt-2">Del diseño a la visualización.</h2>
            </header>
            @foreach ($services as $group => $serviceItems)
                <h3 class="services-group-label home-services-group">{{ $group }}</h3>
                <div class="row g-4 mb-5">
                    @foreach ($serviceItems as $service)
                        <div class="col-md-6 col-xl-3">
                            <article class="service-card {{ $loop->parent->last ? 'service-card--student' : '' }}">
                                <span class="service-card__number" aria-hidden="true">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                <h4 class="service-card__title">{{ $service['name'] }}</h4>
                                <p class="service-card__text">{{ $service['description'] }}</p>
                            </article>
                        </div>
                    @endforeach
                </div>
            @endforeach
            <a class="btn btn-dark home-button" href="{{ route('service-requests.create') }}">Cuéntanos qué necesitas <span aria-hidden="true">→</span></a>
        </div>
    </section>

    <section id="equipo" class="section-block" aria-labelledby="team-title">
        <div class="container">
            <header class="section-header mb-5">
                <span class="section-label">Quiénes somos</span>
                <h2 id="team-title" class="section-title mt-2">El equipo detrás del proyecto.</h2>
            </header>
            <div class="row g-4">
                @foreach (config('studio.team') as $member)
                    <div class="col-lg-6">
                        <article class="director-card">
                            <div class="director-card__avatar" aria-hidden="true">{{ $member['initials'] }}</div>
                            <div class="director-card__info">
                                <h3 class="director-card__name">{{ $member['name'] }}</h3>
                                <p class="director-card__role">{{ $member['qualification'] }}</p>
                                <p class="director-card__studio">{{ $member['role'] }}</p>
                                <ul class="director-card__specs">
                                    @foreach ($member['specialties'] as $specialty)<li>{{ $specialty }}</li>@endforeach
                                </ul>
                                <a class="director-card__pill" href="{{ $member['whatsapp'] }}" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp de {{ $member['name'] }} (abre otra pestaña)">Contactar por WhatsApp <span aria-hidden="true">↗</span></a>
                            </div>
                        </article>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section
    id="proyectos"
    class="section-block section-block--gray"
    aria-labelledby="projects-title"
>
    <div class="container">
        <div class="section-header text-center mb-5">
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
                    <p class="text-center">
                        Próximamente compartiremos nuestros proyectos.
                    </p>
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
                    <h2 id="contact-title" class="section-title section-title--light mt-3">¿Tienes un proyecto<br>en mente?</h2>
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

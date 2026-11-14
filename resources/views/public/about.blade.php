@extends('layouts.app')

@section('title', 'Nosotros | ARAT STUDIO')

@section('content')
    <section class="section-block public-info-page" aria-labelledby="about-title">
        <div class="container">
            <header class="section-header text-center mb-5">
                <span class="section-label">Nuestro enfoque</span>
                <h1 id="about-title" class="section-title mt-2">Sobre el estudio</h1>
            </header>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h2 class="studio-info__title mb-4">Diseño con propósito.<br>Arquitectura con precisión.</h2>
                    <p class="section-body">
                        ARAT STUDIO es un estudio de arquitectura y diseño con sede en Lima, Perú, enfocado en proyectos residenciales, comerciales e institucionales.
                    </p>
                    <p class="section-body">
                        Nuestra práctica integra arquitectura, diseño de interiores, modelado BIM y renderización fotorrealista, acompañando cada proyecto desde el concepto hasta la comunicación visual.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="section-block section-block--gray" aria-labelledby="team-title">
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
@endsection

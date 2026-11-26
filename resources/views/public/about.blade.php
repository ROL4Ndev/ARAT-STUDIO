@extends('layouts.app')

@section('title', 'Nosotros | ARAT STUDIO')

@section('content')
    <section class="about-opening" aria-labelledby="about-title">
        <div class="container">
            @include('public.partials.studio-intro', ['fullPage' => true])
        </div>
    </section>

    <section class="studio-practice section-block" aria-labelledby="practice-title">
        <div class="container studio-practice__grid {{ $studioProject ? '' : 'studio-practice__grid--text' }}">
            @if ($studioProject)
                <figure class="studio-practice__figure" data-reveal="image">
                    <img src="{{ $studioProject->cover_image_url }}" alt="Vista interior de {{ $studioProject->title }}"
                        width="{{ $studioProject->coverMedia?->width ?? 1400 }}" height="{{ $studioProject->coverMedia?->height ?? 1050 }}" loading="lazy">
                    <figcaption>{{ $studioProject->title }} · {{ $studioProject->projectCategory?->name }}</figcaption>
                </figure>
            @endif
            <div class="studio-practice__copy" data-reveal="copy">
                <p class="section-label">Nuestra práctica</p>
                <h2 id="practice-title">Del concepto<br>a cada detalle.</h2>
                <p>Nuestra práctica integra arquitectura, diseño de interiores, modelado BIM y renderización
                    fotorrealista, acompañando cada proyecto desde el concepto hasta la comunicación visual.</p>
                <div class="studio-practice__disciplines" aria-label="Áreas de trabajo">
                    <span>Arquitectura</span><span>Interiores</span><span>Modelado BIM</span><span>Visualización 3D</span>
                </div>
                <a class="editorial-link" href="{{ route('services') }}">Explora nuestros servicios</a>
            </div>
        </div>
    </section>

    <section id="equipo" class="section-block studio-team" aria-labelledby="team-title">
        <div class="container">
            <header class="studio-team__header">
                <div data-reveal="title">
                    <p class="section-label">Quiénes somos</p>
                    <h2 id="team-title" class="section-title">Dos miradas.<br>Una misma visión.</h2>
                </div>
                <p class="section-body">El equipo detrás del proyecto. Arquitectura, precisión técnica y
                    comunicación visual desde una práctica compartida.</p>
            </header>
            @include('public.partials.team')
        </div>
    </section>

    @include('public.partials.project-invitation', ['id' => 'about-next-title'])
@endsection

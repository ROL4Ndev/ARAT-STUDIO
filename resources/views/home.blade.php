@extends('layouts.app')

@section('title', 'ARAT STUDIO | Arquitectura y Diseño en Lima')

@section('content')
    <section
        id="inicio"
        class="hero d-flex align-items-center justify-content-center"
        aria-labelledby="home-title"
    >
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

            <a href="#sobre" class="btn btn-outline-light btn-hero mt-4">
                Conocer el estudio
            </a>
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

                    <p class="section-body mb-0">
                        Nuestra práctica integra arquitectura, diseño
                        de interiores, modelado BIM y renderización
                        fotorrealista, acompañando cada proyecto desde
                        el concepto hasta la comunicación visual.
                    </p>
                </div>
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
                    <article class="project-card project-card--preview">
                        <div class="project-card__media">
                            <img
                                src="{{ asset($project->cover_image) }}"
                                alt="{{ $project->title }}"
                                loading="lazy"
                                width="800"
                                height="600"
                            >
                        </div>

                        <div class="project-card__body">
                            <a href="{{ route('projects.show', ['project' => $project->slug]) }}"
                                class="project-detail-link"
                                aria-label="Ver proyecto: {{ $project->title }}"
                            > 
                                Ver proyecto <span aria-hidden="true">→</span>
                            </a>
                            <p class="project-category">
                                {{ $project->category }}
                            </p>

                            <h3 class="project-card__title">
                                {{ $project->title }}
                            </h3>

                            <p class="project-card__desc">
                                {{ $project->description }}
                            </p>
                        </div>
                    </article>
                </div>
            @empty
                <div class="col-12">
                    <p class="text-center">
                        Próximamente compartiremos nuestros proyectos.
                    </p>
                </div>
            @endforelse
        </div>
    </div>
</section>
@endsection
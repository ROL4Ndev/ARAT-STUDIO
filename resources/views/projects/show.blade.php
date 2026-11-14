@extends('layouts.app')

@section('title', $project->title . ' | ARAT STUDIO')

@section('content')
    <section class="project-detail" aria-labelledby="project-title">
        <div class="container">
            <a
                href="{{ route('projects.index') }}"
                class="project-back-link"
            >
                <span aria-hidden="true">←</span> Volver a proyectos
            </a>

            <header class="project-detail__header">
                <p class="project-category">
                    {{ $project->projectCategory?->name ?? 'Sin categoría' }}
                </p>

                <h1 id="project-title" class="project-detail__title">
                    {{ $project->title }}
                </h1>

                <p class="project-detail__description">
                    {{ $project->description }}
                </p>
            </header>

            <figure class="project-detail__figure">
                <img
                    src="{{ $project->cover_image_url }}"
                    alt="Vista de {{ $project->title }}"
                    class="project-detail__image"
                    width="1600"
                    height="1200"
                    fetchpriority="high"
                >
                <figcaption>
                    {{ $project->title }} · {{ $project->projectCategory?->name ?? 'Sin categoría' }}
                </figcaption>
            </figure>
            <section class="next-action" aria-labelledby="project-next-title">
                <h2 id="project-next-title">Demos forma a tu próximo espacio.</h2>
                <p>Cuéntanos qué necesitas. El equipo revisará el alcance de tu proyecto y preparará una propuesta.</p>
                <a class="btn btn-dark mt-3" href="{{ route('service-requests.create') }}">Solicitar un proyecto</a>
            </section>
        </div>
    </section>
@endsection

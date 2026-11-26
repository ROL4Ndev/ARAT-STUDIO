@extends('layouts.app')

@section('title', $project->title . ' | ARAT STUDIO')

@section('content')
    <section class="project-detail" aria-labelledby="project-title">
        <div class="container">
            <a href="{{ route('projects.index') }}" class="project-back-link">
                <span aria-hidden="true">←</span> Volver a proyectos
            </a>

            <header class="project-detail__header" data-reveal="title">
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
                <img src="{{ $project->cover_image_url }}" alt="Vista de {{ $project->title }}"
                    class="project-detail__image" width="{{ $project->coverMedia?->width ?? 1600 }}"
                    height="{{ $project->coverMedia?->height ?? 1200 }}" fetchpriority="high">
                <figcaption>
                    {{ $project->title }} · {{ $project->projectCategory?->name ?? 'Sin categoría' }}
                </figcaption>
            </figure>
            @if ($project->images->isNotEmpty())
                <section aria-labelledby="project-gallery-title">
                    <div class="section-header mb-4">
                        <p class="section-label">Una mirada más cerca</p>
                        <h2 id="project-gallery-title">Recorre el proyecto</h2>
                    </div>
                    <div class="project-gallery">
                        @foreach ($project->images as $image)
                            <a href="{{ $image->url }}" data-gallery-image
                                data-caption="{{ $project->title }} · imagen {{ $loop->iteration }} de {{ $loop->count }}">
                                <img src="{{ $image->thumbnail_url }}"
                                    alt="{{ $project->title }} · imagen {{ $loop->iteration }}"
                                    width="{{ $image->width }}" height="{{ $image->height }}" loading="lazy">
                                <span>Ampliar imagen {{ $loop->iteration }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
                <dialog id="project-lightbox" class="project-lightbox" aria-label="Fotografías del proyecto">
                    <button type="button" data-gallery-close>Cerrar galería</button>
                    <img alt="" data-gallery-full>
                    <p data-gallery-caption aria-live="polite"></p>
                    <div><button type="button" data-gallery-previous>Anterior</button><button type="button"
                            data-gallery-next>Siguiente</button></div>
                </dialog>
            @endif
            @if ($related->isNotEmpty())
                <section class="my-5" aria-labelledby="related-title">
                    <p class="section-label">Sigue explorando</p>
                    <h2 id="related-title" class="mb-4">Proyectos relacionados</h2>
                    <div class="row g-4">
                        @foreach ($related as $relatedProject)
                            <div class="col-md-4">@include('layouts.components.project-card', ['project' => $relatedProject, 'headingLevel' => 3])</div>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    </section>
    @include('public.partials.project-invitation', ['id' => 'project-next-title'])
@endsection

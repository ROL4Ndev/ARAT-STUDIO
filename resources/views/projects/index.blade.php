@extends('layouts.app')

@section('title', 'Portafolio | ARAT STUDIO')

@section('content')
    <section class="portfolio-page" aria-labelledby="portfolio-title">
        <div class="container">
            <header class="portfolio-page__header">
                <span class="section-label">Nuestro trabajo</span>

                <h1 id="portfolio-title" class="project-detail__title mt-3">
                    Portafolio
                </h1>

                <p class="section-body">
                    Explora nuestros proyectos de arquitectura,
                    diseño interior y visualización.
                </p>
            </header>

            <div class="row g-4">
                @forelse ($projects as $project)
                    <div class="col-md-6 col-lg-4">
                        @include('layouts.components.project-card', [
                            'project' => $project,
                            'headingLevel' => 2,
                        ])
                    </div>
                @empty
                    <div class="col-12">
                        <p>Todavía no hay proyectos publicados.</p>
                    </div>
                @endforelse
            </div>

            @if ($projects->hasPages())
                <nav class="portfolio-pagination" aria-label="Páginas del portafolio">
                    @if ($projects->onFirstPage())
                        <span aria-disabled="true">Anterior</span>
                    @else
                        <a href="{{ $projects->previousPageUrl() }}" rel="prev">
                            Anterior
                        </a>
                    @endif

                    <span>
                        Página {{ $projects->currentPage() }}
                        de {{ $projects->lastPage() }}
                    </span>

                    @if ($projects->hasMorePages())
                        <a href="{{ $projects->nextPageUrl() }}" rel="next">
                            Siguiente
                        </a>
                    @else
                        <span aria-disabled="true">Siguiente</span>
                    @endif
                </nav>
            @endif
        </div>
    </section>
@endsection
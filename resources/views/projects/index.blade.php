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

            <form
                action="{{ route('projects.index') }}"
                method="GET"
                class="portfolio-filters"
            >
                <div class="portfolio-filters__field">
                    <label for="category" class="form-label">
                        Categoría
                    </label>

                    <select
                        id="category"
                        name="category"
                        class="form-select"
                        @error('category')
                            aria-invalid="true"
                            aria-describedby="category-error"
                        @enderror
                    >
                        <option value="">Todas las categorías</option>

                        @foreach ($categories as $category)
                            <option
                                value="{{ $category->slug }}"
                                @selected($selectedCategory === $category->slug)
                            >
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>

                    @error('category')
                        <p id="category-error" class="text-danger mt-2 mb-0">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <button type="submit" class="btn btn-dark">
                    Filtrar
                </button>

                @if ($selectedCategory !== '')
                    <a href="{{ route('projects.index') }}" class="portfolio-clear">
                        Quitar filtro
                    </a>
                @endif
            </form>

            <p class="portfolio-results">
                {{ $projects->total() }}
                {{ $projects->total() === 1 ? 'proyecto encontrado' : 'proyectos encontrados' }}
            </p>

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
                        <p>No hay proyectos disponibles para esta selección.</p>

                        @if ($selectedCategory !== '')
                            <a href="{{ route('projects.index') }}" class="project-back-link">
                                Ver todos los proyectos
                            </a>
                        @endif
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
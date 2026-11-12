<article class="project-card project-card--preview">
    <div class="project-card__media">
        <img
            src="{{ $project->cover_image_url }}"
            alt="Vista de {{ $project->title }}"
            loading="lazy"
            width="800"
            height="600"
        >
    </div>

    <div class="project-card__body">
        <p class="project-category">
            {{ $project->projectCategory?->name ?? 'Sin categoría' }}
        </p>

        @if (($headingLevel ?? 3) === 2)
            <h2 class="project-card__title">
                {{ $project->title }}
            </h2>
        @else
            <h3 class="project-card__title">
                {{ $project->title }}
            </h3>
        @endif

        <p class="project-card__desc">
            {{ $project->description }}
        </p>

        <a
            href="{{ route('projects.show', ['project' => $project->slug]) }}"
            class="project-detail-link"
            aria-label="Ver proyecto: {{ $project->title }}"
        >
            Ver proyecto <span aria-hidden="true">→</span>
        </a>
    </div>
</article>
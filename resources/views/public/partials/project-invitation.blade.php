<section class="project-invitation section-block" aria-labelledby="{{ $id }}">
    <div class="container project-invitation__grid">
        <div data-reveal="title">
            <p class="section-label">Tu próximo espacio</p>
            <h2 id="{{ $id }}">Todo comienza<br>con una idea.</h2>
        </div>
        <div class="project-invitation__actions">
            <p>Cuéntanos qué necesitas. El equipo revisará el alcance de tu proyecto para preparar una propuesta.</p>
            <a class="btn btn-dark" href="{{ route('service-requests.create') }}">Solicitar una evaluación</a>
            <a class="editorial-link" href="{{ route('projects.index') }}">Explorar el portafolio</a>
        </div>
    </div>
</section>

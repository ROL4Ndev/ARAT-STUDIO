<div class="studio-intro">
    <div class="studio-intro__copy">
        <p class="section-label">Nuestro enfoque</p>
        @if ($fullPage ?? false)
            <h1 id="about-title" class="section-title">Sobre el estudio</h1>
            <h2 class="studio-info__title">Diseño con propósito.<br>Arquitectura con precisión.</h2>
        @else
            <h2 id="about-title" class="section-title">Sobre el estudio</h2>
            <h3 class="studio-info__title">Diseño con propósito.<br>Arquitectura con precisión.</h3>
        @endif
        <p class="section-body">ARAT STUDIO es un estudio de arquitectura y diseño con sede en Lima, Perú, enfocado en
            proyectos residenciales, comerciales e institucionales.</p>
        @if ($fullPage ?? false)
            <p class="section-body">Nuestra práctica integra arquitectura, diseño de interiores, modelado BIM y
                renderización fotorrealista, acompañando cada proyecto desde el concepto hasta la comunicación visual.
            </p>
            <a href="#equipo" class="home-intro-link">Conoce al equipo <span aria-hidden="true">↓</span></a>
        @else
            <a href="{{ route('about') }}" class="home-intro-link">Conoce el estudio <span
                    aria-hidden="true">→</span></a>
        @endif
    </div>
    @include('public.partials.studio-film', [
        'id' => 'studio-process-video',
        'file' => 'estudio-arthouse',
        'label' => 'proceso arquitectónico',
        'posterAlt' => 'Escena referencial de revisión de planos sobre una mesa de trabajo',
    ])
</div>

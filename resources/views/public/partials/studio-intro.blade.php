@if ($fullPage ?? false)
    <div class="about-opening__eyebrow">
        <p class="section-label">Nosotros · Sobre el estudio</p>
        <p class="section-label">ARAT STUDIO / Lima, Perú</p>
    </div>
    <h1 id="about-title" class="about-opening__title" data-reveal="title">
        Diseño con propósito.<br> <span>Arquitectura</span> con precisión.
    </h1>
    <div class="about-opening__media">
        @include('public.partials.studio-film', [
            'id' => 'studio-process-video',
            'file' => 'estudio-arthouse',
            'label' => 'proceso arquitectónico',
            'posterAlt' => 'Escena referencial de revisión de planos sobre una mesa de trabajo',
        ])
        <div class="about-opening__note" data-reveal="copy">
            <p class="section-label">Espacio. Forma. Propósito.</p>
            <p>ARAT STUDIO es un estudio de arquitectura y diseño con sede en Lima, Perú, enfocado en
                proyectos residenciales, comerciales e institucionales.</p>
            <a href="#equipo" class="editorial-link">Conoce al equipo</a>
        </div>
    </div>
@else
    <div class="studio-intro">
        <div class="studio-intro__copy" data-reveal="copy">
            <p class="section-label">Nuestro enfoque</p>
            <h2 id="about-title" class="section-title">Sobre el estudio</h2>
            <h3 class="studio-info__title">Diseño con propósito.<br>Arquitectura con precisión.</h3>
            <p class="section-body">ARAT STUDIO es un estudio de arquitectura y diseño con sede en Lima, Perú, enfocado en
                proyectos residenciales, comerciales e institucionales.</p>
            <a href="{{ route('about') }}" class="editorial-link">Conoce el estudio</a>
        </div>
        @include('public.partials.studio-film', [
            'id' => 'studio-process-video',
            'file' => 'estudio-arthouse',
            'label' => 'proceso arquitectónico',
            'posterAlt' => 'Escena referencial de revisión de planos sobre una mesa de trabajo',
        ])
    </div>
@endif

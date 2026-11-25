<footer class="site-footer">
    <div class="container">
        <div class="site-footer__content">
            <a class="site-footer__brand" href="{{ route('home') }}" aria-label="ARAT STUDIO, inicio">
                <img src="{{ asset('img/LOGO.png') }}" alt="" width="42" height="42">
                <span>ARAT STUDIO<small>Arquitectura · Diseño · Visualización 3D</small></span>
            </a>
            <nav class="site-footer__links" aria-label="Enlaces del pie de página">
                <a href="{{ route('about') }}">Nosotros</a>
                <a href="{{ route('services') }}">Servicios</a>
                <a href="{{ route('projects.index') }}">Portafolio</a>
                <a href="{{ route('service-requests.create') }}">Solicitar proyecto</a>
                <a href="{{ route('contact') }}">Contacto</a>
                <a href="{{ route('client.access') }}">Consultar mi expediente</a>
            </nav>
            <div class="site-footer__contact">
                <a href="mailto:{{ config('studio.email') }}">{{ config('studio.email') }}</a>
                <a href="tel:{{ config('studio.phone_link') }}">{{ config('studio.phone') }}</a>
            </div>
        </div>
        <div class="site-footer__bottom">
            <p class="site-footer__copy mb-0">&copy; {{ now()->year }} ARAT STUDIO · Lima, Perú</p>
            @if(auth()->check() && in_array(auth()->user()->role, ['admin', 'gestor'], true))
                <a href="{{ route('panel') }}">Volver al panel</a>
            @else
                <a href="{{ route('login') }}">Acceso al equipo</a>
            @endif
        </div>
    </div>
</footer>

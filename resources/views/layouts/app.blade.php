<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', 'ARAT STUDIO | Arquitectura y Diseño')</title>
    <meta
        name="description"
        content="ARAT STUDIO: arquitectura, diseño de interiores y visualización 3D en Lima, Perú."
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Kodchasan:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="{{ asset('css/arat.css') }}">
    <link rel="stylesheet" href="{{ asset('css/presentation.css') }}">
</head>
<body>
    <a href="#contenido-principal" class="skip-link">Saltar al contenido</a>
    <nav
        id="navbar"
        class="navbar fixed-top"
        aria-label="Navegación principal"
    >
        <div class="container">
            <a
                class="navbar-brand navbar-brand--combo"
                href="{{ route('home') }}"
                aria-label="ARAT STUDIO, inicio"
            >
                <img
                    src="{{ asset('img/LOGO.png') }}"
                    alt=""
                    class="navbar-brand__logo"
                >
                <span class="navbar-brand__text">ARAT STUDIO</span>
            </a>
            <div class="site-navigation site-navigation--desktop">
                @include('layouts.components.navigation-links')
            </div>
            <details id="mobile-navigation" class="site-menu">
                <summary>Menú <span aria-hidden="true">☰</span></summary>
                <div class="site-navigation">
                    @include('layouts.components.navigation-links')
                </div>
            </details>
        </div>
    </nav>

    <main id="contenido-principal" tabindex="-1">
        @yield('content')
    </main>

    @include('layouts.components.footer')
    <script src="{{ asset('js/navigation.js') }}" defer></script>
    <script src="{{ asset('js/interface.js') }}" defer></script>
    @stack('scripts')
</body>
</html>

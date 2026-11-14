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

    @php($internal = auth()->check() && request()->routeIs('admin.*', 'management.*', 'commercial.*', 'profile.*'))
    @vite([$internal ? 'resources/css/panel.css' : 'resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="{{ $internal ? 'internal' : 'public-site' }}">
    <a href="#contenido-principal" class="skip-link">Saltar al contenido</a>
    @if ($internal)
        @include('layouts.components.workspace-navigation')
    @else
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
            <button type="button" class="site-menu-toggle" aria-controls="site-navigation" aria-expanded="true" hidden>
                Menú <span aria-hidden="true">☰</span>
            </button>
            <div id="site-navigation" class="site-navigation">
                @include('layouts.components.navigation-links')
            </div>
        </div>
    </nav>
    @endif

    <main id="contenido-principal" tabindex="-1">
        @yield('content')
    </main>

    @include('layouts.components.footer')
    @stack('scripts')
</body>
</html>

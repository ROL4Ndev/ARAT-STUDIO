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
</head>
<body>
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
                <div class="site-navigation">
                     <a
                        href="{{ route('home') }}"
                        @if (request()->routeIs('home')) aria-current="page" @endif
                     >
                        Inicio
                    </a>

                    <a
                        href="{{ route('projects.index') }}"
                        @if (request()->routeIs('projects.*')) aria-current="page" @endif
                    >
                        Portafolio
                    </a>

                    <a
                        href="{{ route('quotation.show') }}"
                        @if (request()->routeIs('quotation.show')) aria-current="page" @endif
                    >
                        Cotizador
                    </a>

                    <a href="{{ route('service-requests.create') }}">Solicitar proyecto</a>
                    @guest
                        <a href="{{ route('login') }}">Ingresar</a>
                    @else
                        <a href="{{ route('profile.edit') }}">Mi perfil</a>
                        @if (auth()->user()->role === 'admin')
                            <a href="{{ route('admin.dashboard') }}">Administración</a>
                            <a href="{{ route('management.dashboard') }}">Gestión</a>
                        @elseif (auth()->user()->role === 'gestor')
                            <a href="{{ route('management.dashboard') }}">Gestión</a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}" class="site-navigation__logout">
                            @csrf
                            <button type="submit" class="site-navigation__button">Cerrar sesión</button>
                        </form>
                    @endguest
                </div>
        </div>
    </nav>

    <main id="contenido-principal">
        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="container text-center">
            <p class="site-footer__copy mb-0">
                &copy; {{ date('Y') }} ARAT STUDIO
            </p>
        </div>
    </footer>
    @stack('scripts')
</body>
</html>

<a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>Inicio</a>
<details class="site-submenu" @if(request()->routeIs('about', 'services', 'contact')) open @endif>
    <summary>Estudio</summary>
    <div class="site-submenu__links">
        <a href="{{ route('about') }}" @if(request()->routeIs('about')) aria-current="page" @endif>Nosotros</a>
        <a href="{{ route('services') }}" @if(request()->routeIs('services')) aria-current="page" @endif>Servicios</a>
        <a href="{{ route('contact') }}" @if(request()->routeIs('contact')) aria-current="page" @endif>Contacto</a>
    </div>
</details>
<a href="{{ route('projects.index') }}" @if(request()->routeIs('projects.*')) aria-current="page" @endif>Portafolio</a>
<a href="{{ route('quotation.show') }}" @if(request()->routeIs('quotation.show')) aria-current="page" @endif>Cotizador</a>
<a class="site-navigation__primary" href="{{ route('service-requests.create') }}" @if(request()->routeIs('service-requests.*')) aria-current="page" @endif>Solicitar proyecto</a>
<a href="{{ route('client.access') }}" @if(request()->routeIs('client.*')) aria-current="page" @endif>Consultar mi expediente</a>
@auth
    <details class="site-submenu">
        <summary>Mi cuenta</summary>
        <div class="site-submenu__links">
            <a href="{{ route('profile.edit') }}" @if(request()->routeIs('profile.*')) aria-current="page" @endif>Mi perfil</a>
            @if(in_array(auth()->user()->role, ['admin', 'gestor'], true))
                <a href="{{ route('panel') }}">Volver al panel</a>
            @endif
            <form method="POST" action="{{ route('logout') }}" class="site-navigation__logout">
                @csrf
                <button type="submit" class="site-navigation__button">Cerrar sesión</button>
            </form>
        </div>
    </details>
@endauth

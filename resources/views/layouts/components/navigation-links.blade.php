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
@guest
    <details class="site-submenu" @if(request()->routeIs('client.*', 'login')) open @endif>
        <summary>Acceso</summary>
        <div class="site-submenu__links">
            <a href="{{ route('client.access') }}" @if(request()->routeIs('client.*')) aria-current="page" @endif>Mi expediente</a>
            <a href="{{ route('login') }}" @if(request()->routeIs('login')) aria-current="page" @endif>Ingresar</a>
        </div>
    </details>
@else
    <details class="site-submenu">
        <summary>Mi cuenta</summary>
        <div class="site-submenu__links">
            <a href="{{ route('profile.edit') }}" @if(request()->routeIs('profile.*')) aria-current="page" @endif>Mi perfil</a>
            @if(auth()->user()->role === 'admin')
                <a href="{{ route('admin.dashboard') }}" @if(request()->routeIs('admin.*')) aria-current="page" @endif>Administración</a>
            @endif
            @if(in_array(auth()->user()->role, ['admin', 'gestor'], true))
                <a href="{{ route('management.dashboard') }}" @if(request()->routeIs('management.*', 'commercial.*')) aria-current="page" @endif>Gestión</a>
            @endif
            <form method="POST" action="{{ route('logout') }}" class="site-navigation__logout">
                @csrf
                <button type="submit" class="site-navigation__button">Cerrar sesión</button>
            </form>
        </div>
    </details>
@endguest

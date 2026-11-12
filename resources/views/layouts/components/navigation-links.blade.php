<a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>Inicio</a>
<a href="{{ route('projects.index') }}" @if(request()->routeIs('projects.*')) aria-current="page" @endif>Portafolio</a>
<a href="{{ route('quotation.show') }}" @if(request()->routeIs('quotation.show')) aria-current="page" @endif>Cotizador</a>
<a href="{{ route('service-requests.create') }}" @if(request()->routeIs('service-requests.*')) aria-current="page" @endif>Solicitar proyecto</a>
@guest
    <a href="{{ route('client.access') }}">Mi expediente</a>
    <a href="{{ route('login') }}" @if(request()->routeIs('login')) aria-current="page" @endif>Ingresar</a>
@else
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
@endguest

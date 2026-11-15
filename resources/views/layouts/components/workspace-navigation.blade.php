<aside id="workspace-navigation" class="workspace-sidebar" aria-label="Panel de trabajo">
    <a class="navbar-brand navbar-brand--combo" href="{{ route('home') }}" aria-label="ARAT STUDIO, inicio">
        <img src="{{ asset('img/LOGO.png') }}" class="navbar-brand__logo" alt="" width="40" height="40">
        <span class="navbar-brand__text">ARAT STUDIO</span>
    </a>
    <p class="workspace-sidebar__label">Espacio de trabajo</p>
    <nav aria-label="Navegación interna">
        @if (in_array(auth()->user()->role, ['admin', 'gestor'], true))
            <a href="{{ route('management.dashboard') }}" @if(request()->routeIs('management.*','commercial.*')) aria-current="page" @endif>Gestión comercial</a>
        @endif
        @if (auth()->user()->role === 'admin')
            <a href="{{ route('admin.team.index') }}" @if(request()->routeIs('admin.team.*')) aria-current="page" @endif>Equipo · Gestores</a>
            <a href="{{ route('admin.dashboard') }}" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif>Resumen del panel</a>
            @foreach (['projects' => 'Proyectos', 'categories' => 'Categorías', 'services' => 'Servicios', 'tariffs' => 'Tarifas'] as $kind => $label)
                <a href="{{ route('admin.'.$kind.'.index') }}" @if(request()->routeIs('admin.'.$kind.'.*')) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        @endif
        <a href="{{ route('profile.edit') }}" @if(request()->routeIs('profile.*')) aria-current="page" @endif>Mi perfil</a>
    </nav>
    <div class="workspace-sidebar__account">
        <strong>{{ auth()->user()->name }}</strong>
        <span>{{ match(auth()->user()->role) { 'admin' => 'Administrador', 'gestor' => 'Gestor', default => 'Cuenta personal' } }}</span>
        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Cerrar sesión</button></form>
    </div>
</aside>
<header class="workspace-topbar">
    <button class="workspace-menu" type="button" data-workspace-toggle aria-expanded="false" aria-controls="workspace-navigation">Menú del panel</button>
    <span>ARAT STUDIO <span class="text-muted">/ Panel interno</span></span>
    <a href="{{ route('home') }}">Ver sitio público <span aria-hidden="true">↗</span></a>
</header>

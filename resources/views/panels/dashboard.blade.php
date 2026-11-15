@extends('layouts.app')

@section('title', $panel . ' | ARAT STUDIO')

@section('content')
    <section class="workspace-view" aria-labelledby="dashboard-title">
        <header class="workspace-hero">
            <div class="container">
                <p class="section-label">Panel interno</p>
                <h1 id="dashboard-title">Panel de {{ $panel }}</h1>
                <p>Hola, {{ auth()->user()->name }}. Organiza los proyectos del estudio y acompaña cada nueva solicitud.</p>
                <a class="btn btn-dark mt-2" href="{{ route('management.dashboard') }}">Abrir Gestión comercial</a>
            </div>
        </header>
        <div class="workspace-content">
            <div class="container">
                @include('shared.dashboard-insights')
                <nav aria-label="Navegación del panel" class="dashboard-navigation">
                    @if (auth()->user()->role === 'admin')
                        <a class="dashboard-link" href="{{ route('admin.team.index') }}">
                            <strong>Equipo · Gestores</strong>
                            <span>Organiza responsables, accesos y expedientes asignados.</span>
                        </a>
                        <a class="dashboard-link" href="{{ route('admin.projects.index') }}">
                            <strong>Administrar proyectos</strong>
                            <span>Publica el trabajo del estudio y actualiza sus portadas.</span>
                        </a>
                        <a class="dashboard-link" href="{{ route('admin.categories.index') }}">
                            <strong>Administrar categorías</strong>
                            <span>Organiza el portafolio para que sea fácil de explorar.</span>
                        </a>
                        <a class="dashboard-link" href="{{ route('admin.services.index') }}">
                            <strong>Administrar servicios</strong>
                            <span>Define qué ofrece el estudio y cómo se estima cada servicio.</span>
                        </a>
                        <a class="dashboard-link" href="{{ route('admin.tariffs.index') }}">
                            <strong>Administrar tarifas</strong>
                            <span>Revisa los importes vigentes y prepara nuevas versiones.</span>
                        </a>
                    @endif
                </nav>
                <section class="next-action mt-4">
                    <h2>Una solicitud, un recorrido claro</h2>
                    <p>Asigna un responsable, evalúa el proyecto y prepara la propuesta oficial. El expediente conserva las
                        correcciones, las versiones y la respuesta del cliente.</p>
                </section>
            </div>
        </div>
    </section>
@endsection

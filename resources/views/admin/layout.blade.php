@extends('layouts.app')

@section('content')
    <section class="admin-page" aria-labelledby="admin-page-title">
        <div class="container">
            <header class="admin-page__header">
                <p class="section-label">Administración</p>
                <h1 id="admin-page-title" class="project-detail__title">@yield('admin-title')</h1>
                <nav class="admin-navigation" aria-label="Administración del sitio">
                    <a href="{{ route('admin.categories.index') }}">Categorías</a>
                    <a href="{{ route('admin.projects.index') }}">Proyectos</a>
                    <a href="{{ route('admin.services.index') }}">Servicios</a>
                    <a href="{{ route('admin.tariffs.index') }}">Tarifas</a>
                    <a href="{{ route('admin.dashboard') }}">Panel</a>
                </nav>
            </header>

            @if (session('success'))
                <div class="alert alert-success" role="status">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
            @endif

            @yield('admin-content')
        </div>
    </section>
@endsection

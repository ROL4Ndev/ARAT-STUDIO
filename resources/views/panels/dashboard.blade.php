@extends('layouts.app')

@section('title', $panel.' | ARAT STUDIO')

@section('content')
    <section class="account-page" aria-labelledby="dashboard-title">
        <div class="container account-page__container">
            <div class="account-card dashboard-card">
                <p class="section-label">Panel interno</p>
                <h1 id="dashboard-title" class="account-card__title">Panel de {{ $panel }}</h1>
                <p>Hola, {{ auth()->user()->name }}.</p>
                <p>Tu perfil: <strong>{{ $role }}</strong></p>

                <nav aria-label="Navegación del panel" class="dashboard-navigation">
                    @if (auth()->user()->role === 'admin')
                        <a class="btn btn-outline-dark" href="{{ route('admin.dashboard') }}">Administración</a>
                        <a class="btn btn-dark" href="{{ route('admin.categories.index') }}">Administrar categorías</a>
                        <a class="btn btn-dark" href="{{ route('admin.projects.index') }}">Administrar proyectos</a>
                    @endif
                    <a class="btn btn-outline-dark" href="{{ route('management.dashboard') }}">Gestión</a>
                    <a class="btn btn-outline-dark" href="{{ route('profile.edit') }}">Mi perfil</a>
                </nav>

                <div class="dashboard-pending">
                    <h2 class="account-card__subtitle">Módulos pendientes</h2>
                    <ul>
                        <li>Administración de categorías y proyectos.</li>
                        <li>Registro y evaluación de solicitudes.</li>
                        <li>Emisión de cotizaciones oficiales.</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>
@endsection

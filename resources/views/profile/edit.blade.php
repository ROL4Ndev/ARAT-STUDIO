@extends('layouts.app')

@section('title', 'Mi perfil | ARAT STUDIO')

@section('content')
    <section class="account-page" aria-labelledby="profile-title">
        <div class="container account-page__container">
            <div class="account-card">
                <p class="section-label">Cuenta</p>
                <h1 id="profile-title" class="account-card__title">Mi perfil</h1>
                <p>Rol: {{ match ($user->role) { 'admin' => 'Administrador', 'gestor' => 'Gestor', default => 'Sin acceso interno' } }}</p>

                @if (session('status'))
                    <div class="alert alert-success" role="status">{{ session('status') }}</div>
                @endif

                <h2 class="account-card__subtitle">Datos personales</h2>
                <form method="POST" action="{{ route('profile.update') }}" class="account-form">
                    @csrf
                    @method('PUT')

                    <div class="account-form__field">
                        <label for="name" class="form-label">Nombre</label>
                        <input
                            id="name"
                            name="name"
                            type="text"
                            value="{{ old('name', $user->name) }}"
                            class="form-control @error('name') is-invalid @enderror"
                            autocomplete="name"
                            required
                            aria-describedby="@error('name') name-error @enderror"
                        >
                        @error('name')
                            <p id="name-error" class="invalid-feedback d-block" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="account-form__field">
                        <label for="email" class="form-label">Correo electrónico</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email', $user->email) }}"
                            class="form-control @error('email') is-invalid @enderror"
                            autocomplete="email"
                            required
                            aria-describedby="@error('email') email-error @enderror"
                        >
                        @error('email')
                            <p id="email-error" class="invalid-feedback d-block" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-dark account-form__submit">Guardar perfil</button>
                </form>

                <hr class="account-card__divider">

                <h2 class="account-card__subtitle">Cambiar contraseña</h2>
                <form method="POST" action="{{ route('profile.password.update') }}" class="account-form">
                    @csrf
                    @method('PUT')

                    <div class="account-form__field">
                        <label for="current_password" class="form-label">Contraseña actual</label>
                        <input
                            id="current_password"
                            name="current_password"
                            type="password"
                            class="form-control @error('current_password') is-invalid @enderror"
                            autocomplete="current-password"
                            required
                            aria-describedby="@error('current_password') current-password-error @enderror"
                        >
                        @error('current_password')
                            <p id="current-password-error" class="invalid-feedback d-block" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="account-form__field">
                        <label for="password" class="form-label">Nueva contraseña</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            class="form-control @error('password') is-invalid @enderror"
                            autocomplete="new-password"
                            required
                            aria-describedby="@error('password') password-error @enderror"
                        >
                        @error('password')
                            <p id="password-error" class="invalid-feedback d-block" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="account-form__field">
                        <label for="password_confirmation" class="form-label">Confirmar nueva contraseña</label>
                        <input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            class="form-control"
                            autocomplete="new-password"
                            required
                        >
                    </div>

                    <button type="submit" class="btn btn-dark account-form__submit">Actualizar contraseña</button>
                </form>
            </div>
        </div>
    </section>
@endsection

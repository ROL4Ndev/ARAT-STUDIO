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
                            @error('name') aria-invalid="true" aria-describedby="name-error" @enderror
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
                            @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                        >
                        @error('email')
                            <p id="email-error" class="invalid-feedback d-block" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-dark account-form__submit" data-loading-label="Guardando perfil…">Guardar perfil</button>
                </form>

                <hr class="account-card__divider">

                <h2 class="account-card__subtitle">Cambiar contraseña</h2>
                <form method="POST" action="{{ route('profile.password.update') }}" class="account-form">
                    @csrf
                    @method('PUT')

                    <div class="account-form__field">
                        <label for="current_password" class="form-label">Contraseña actual</label>
                        <div data-password-field>
                            <input
                                id="current_password"
                                name="current_password"
                                type="password"
                                class="form-control @error('current_password') is-invalid @enderror"
                                autocomplete="current-password"
                                required
                                @error('current_password') aria-describedby="current-password-error" @enderror
                            >
                            <button type="button" class="btn btn-outline-dark" data-password-toggle data-password-label="la contraseña actual" aria-controls="current_password" aria-label="Mostrar la contraseña actual" aria-pressed="false">Mostrar</button>
                        </div>
                        @error('current_password')
                            <p id="current-password-error" class="invalid-feedback d-block" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="account-form__field">
                        <label for="new_password" class="form-label">Nueva contraseña</label>
                        <div data-password-field>
                            <input
                                id="new_password"
                                name="password"
                                type="password"
                                class="form-control @error('password') is-invalid @enderror"
                                autocomplete="new-password"
                                required
                                @error('password') aria-describedby="password-error" @enderror
                            >
                            <button type="button" class="btn btn-outline-dark" data-password-toggle data-password-label="la nueva contraseña" aria-controls="new_password" aria-label="Mostrar la nueva contraseña" aria-pressed="false">Mostrar</button>
                        </div>
                        @error('password')
                            <p id="password-error" class="invalid-feedback d-block" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="account-form__field">
                        <label for="password_confirmation" class="form-label">Confirmar nueva contraseña</label>
                        <div data-password-field>
                            <input
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                class="form-control"
                                autocomplete="new-password"
                                required
                            >
                            <button type="button" class="btn btn-outline-dark" data-password-toggle data-password-label="la confirmación de contraseña" aria-controls="password_confirmation" aria-label="Mostrar la confirmación de contraseña" aria-pressed="false">Mostrar</button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-dark account-form__submit" data-loading-label="Actualizando contraseña…">Actualizar contraseña</button>
                </form>
            </div>
        </div>
    </section>
@endsection

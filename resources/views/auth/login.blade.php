@extends('layouts.app')

@section('title', 'Ingresar | ARAT STUDIO')

@section('content')
    <section class="account-page" aria-labelledby="login-title">
        <div class="container account-page__container">
            <div class="account-card">
                <p class="section-label">Acceso interno</p>
                <h1 id="login-title" class="account-card__title">Ingresar</h1>
                <p>Inicia sesión con la cuenta que te proporcionó el administrador.</p>

                <form method="POST" action="{{ route('login.store') }}" class="account-form">
                    @csrf
                    <div class="account-form__field">
                        <label for="email" class="form-label">Correo electrónico</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            class="form-control @error('email') is-invalid @enderror"
                            autocomplete="username"
                            required
                            autofocus
                            aria-describedby="@error('email') email-error @enderror"
                        >
                        @error('email')
                            <p id="email-error" class="invalid-feedback d-block" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="account-form__field">
                        <label for="password" class="form-label">Contraseña</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            class="form-control @error('password') is-invalid @enderror"
                            autocomplete="current-password"
                            required
                            aria-describedby="@error('password') password-error @enderror"
                        >
                        @error('password')
                            <p id="password-error" class="invalid-feedback d-block" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-dark account-form__submit">Ingresar</button>
                </form>
            </div>
        </div>
    </section>
@endsection

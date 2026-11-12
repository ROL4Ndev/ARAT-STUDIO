@extends('layouts.app')

@section('title', 'Acceder a mi expediente | ARAT STUDIO')

@section('content')
    <section class="account-page" aria-labelledby="access-title">
        <div class="container account-page__container">
            <div class="account-card">
                <p class="section-label">Acceso de cliente</p>
                <h1 id="access-title">Acceder a mi expediente</h1>
                <p>No necesitas crear una cuenta. El código por sí solo no permite acceder: verifica el correo asociado a tu solicitud.</p>
                @include('shared.errors')

                <form action="{{ route('client.access.request') }}" method="POST" class="account-form">
                    @csrf
                    <div class="account-form__field">
                        <label for="code" class="form-label">Código de solicitud</label>
                        <input
                            id="code"
                            name="code"
                            class="form-control @error('code') is-invalid @enderror"
                            required
                            maxlength="40"
                            autocomplete="off"
                            value="{{ old('code') }}"
                            @error('code') aria-invalid="true" aria-describedby="code-error" @enderror
                        >
                        @error('code')
                            <p class="form-feedback" id="code-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="account-form__field">
                        <label for="email" class="form-label">Correo electrónico del cliente</label>
                        <input
                            id="email"
                            name="email"
                            class="form-control @error('email') is-invalid @enderror"
                            type="email"
                            required
                            maxlength="255"
                            autocomplete="email"
                            value="{{ old('email') }}"
                            @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                        >
                        @error('email')
                            <p class="form-feedback" id="email-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                    <button class="btn btn-dark account-form__submit" type="submit" data-loading-label="Solicitando enlace…">Solicitar acceso temporal</button>
                </form>

                <p class="mt-3 mb-0">El enlace vence en 15 minutos y solo puede usarse una vez. La sesión verificada dura 30 minutos y puedes cerrarla desde el expediente. En desarrollo, el correo log es una simulación local.</p>
            </div>
        </div>
    </section>
@endsection

@extends('layouts.app')

@section('title', 'Verificar acceso temporal | ARAT STUDIO')

@section('content')
    <section class="account-page" aria-labelledby="verify-title">
        <div class="container account-page__container">
            <div class="account-card">
                <h1 id="verify-title">Verificar acceso temporal</h1>
                <p>Confirma para consumir este enlace de un solo uso. El acceso vence en 15 minutos. Si cambias tu correo, conserva abierta la sesión verificada original.</p>
                <form method="POST" action="{{ route('client.access.consume', $token) }}">
                    @csrf
                    <button class="btn btn-dark" type="submit" data-loading-label="Verificando…">Verificar y abrir expediente</button>
                </form>
                <a class="d-inline-flex mt-3" href="{{ route('client.access') }}">Solicitar otro enlace</a>
            </div>
        </div>
    </section>
@endsection

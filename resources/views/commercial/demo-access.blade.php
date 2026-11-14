@extends('layouts.app')

@section('title', 'Acceso de demostración | ARAT STUDIO')

@section('content')
    <section class="workspace-view" aria-labelledby="demo-access-title">
        <header class="workspace-hero">
            <div class="container">
                <a href="{{ route('commercial.requests.show', $serviceRequest) }}">Volver al expediente {{ $serviceRequest->code }}</a>
                <h1 id="demo-access-title">Acceso de cliente para demostración</h1>
            </div>
        </header>
        <div class="workspace-content">
            <div class="container">
                <section class="workspace-card">
                    <p>Copia este enlace y ábrelo en una ventana de incógnito</p>
                    <p>Abrir otra pestaña normal comparte la sesión interna y no sirve.</p>
                    @if (is_string($demoUrl))
                        <label class="form-label" for="demo-access-link">Enlace temporal (válido 15 minutos y de un solo uso)</label>
                        <div class="d-flex flex-wrap gap-2">
                            <input id="demo-access-link" class="form-control" type="text" value="{{ $demoUrl }}" readonly>
                            <button class="btn btn-dark" type="button" id="copy-demo-access-link">Copiar enlace</button>
                        </div>
                        <p id="copy-demo-access-status" class="form-text" role="status" aria-live="polite"></p>
                    @else
                        <p>El enlace solo se muestra al prepararlo y ya no está disponible en esta solicitud de pantalla.</p>
                    @endif
                </section>
            </div>
        </div>
    </section>
    @if (is_string($demoUrl))
        <script>
            document.getElementById('copy-demo-access-link').addEventListener('click', async () => {
                const field = document.getElementById('demo-access-link');
                const status = document.getElementById('copy-demo-access-status');
                let copied = false;
                try {
                    await navigator.clipboard.writeText(field.value);
                    copied = true;
                } catch {
                    field.select();
                    copied = document.execCommand('copy');
                }
                status.textContent = copied
                    ? 'Enlace copiado.'
                    : 'No se pudo copiar. Selecciona y copia el enlace manualmente.';
            });
        </script>
    @endif
@endsection

@extends('layouts.app')
@section('title', $deliverable->latestVersion->title.' | ARAT STUDIO')
@section('content')
<section class="workspace-view" aria-labelledby="delivery-title-heading">
    <header class="workspace-hero"><div class="container">
        <a href="{{ route('commercial.requests.show', $serviceRequest) }}#entregables">Volver al expediente {{ $serviceRequest->code }}</a>
        <h1 id="delivery-title-heading">{{ $deliverable->latestVersion->title }}</h1>
        <p>{{ $deliverable->code }} · @include('shared.status-label', ['value' => $deliverable->status])</p>
        <p>Cotización aceptada {{ $deliverable->quotation->code }} · Responsable: {{ $serviceRequest->assignee?->name ?? 'Sin asignar' }}</p>
        <nav class="d-flex flex-wrap gap-3" aria-label="Secciones del entregable">
            <a href="#delivery-action">Siguiente paso</a><a href="#delivery-versions">Versiones y respuestas</a>
            @if(config('app.env') === 'local' && config('client_access.demo_enabled'))
                <a href="{{ route('commercial.requests.show', $serviceRequest) }}#demo-client-access">Preparar acceso de demostración del cliente</a>
            @endif
        </nav>
    </div></header>
    <div class="workspace-content"><div class="container">
        @include('shared.errors')
        <section class="workspace-card mb-4" id="delivery-action" aria-labelledby="delivery-next-title">
            @if ($deliverable->status === 'conforme')
                <h2 id="delivery-next-title">Conformidad registrada</h2>
                <p>El entregable está finalizado y es de solo lectura. Puedes consultar y descargar todas las versiones enviadas.</p>
            @elseif ($deliverable->status === 'en_revision')
                <h2 id="delivery-next-title">Pendiente de revisión del cliente</h2>
                <p>La versión {{ $deliverable->latestVersion->number }} ya está disponible en su expediente. Espera su conformidad u observaciones antes de preparar otra versión.</p>
            @else
                @php($version = $deliverable->latestVersion)
                <h2 id="delivery-next-title">{{ $version->sent_at ? 'Preparar versión '.($version->number + 1) : 'Borrador · versión '.$version->number }}</h2>
                @if ($deliverable->status === 'observado')
                    <p>Revisa las observaciones conservadas en el historial. La nueva versión tendrá un archivo propio.</p>
                @endif
                @if (! $version->sent_at && $version->path)
                    <p>Archivo del borrador: {{ $version->original_name }} · {{ number_format($version->bytes / 1024, 1) }} KiB</p>
                    <form method="POST" action="{{ route('commercial.deliverables.send', [$deliverable, $version->number]) }}" data-confirm="¿Enviar esta versión a revisión? Su archivo y contenido se conservarán sin cambios.">
                        @csrf
                        <input type="hidden" name="lock_version" value="{{ $deliverable->lock_version }}">
                        <button class="btn btn-dark" type="submit" data-loading-label="Enviando a revisión…">Enviar a revisión</button>
                    </form>
                    <details class="secondary-details mt-3" @if($errors->any()) open @endif><summary>Editar borrador antes del envío</summary>
                @endif
                <form method="POST" action="{{ route('commercial.deliverables.save', $deliverable) }}" enctype="multipart/form-data" class="mt-3">
                    @csrf
                    <input type="hidden" name="lock_version" value="{{ $deliverable->lock_version }}">
                    @include('deliverables.fields')
                    @if (! $version->sent_at && $version->path)<p class="form-text">Si no adjuntas otro archivo se conserva el del borrador.</p>@endif
                    <button class="btn {{ ! $version->sent_at && $version->path ? 'btn-outline-dark' : 'btn-dark' }} mt-3" type="submit" data-loading-label="Guardando borrador…">Guardar borrador</button>
                </form>
                @if (! $version->sent_at && $version->path)</details>@endif
            @endif
        </section>
        <section class="workspace-card" id="delivery-versions" aria-labelledby="delivery-history-title">
            <h2 id="delivery-history-title">Versiones y respuestas</h2>
            @foreach ($deliverable->versions->sortByDesc('number') as $version)
                <article class="border-top pt-4 mt-4">
                    <h3>Versión {{ $version->number }} · {{ $version->sent_at ? 'Enviada' : 'Borrador interno' }}</h3>
                    <h4>{{ $version->title }}</h4>
                    <p class="long-form-text preserve-lines">{{ $version->description }}</p>
                    <p class="form-text">Preparada por {{ $version->author->name }} · {{ $version->created_at->timezone('America/Lima')->format('d/m/Y H:i') }} (Lima)</p>
                    @if ($version->sent_at)<p>Enviada el {{ $version->sent_at->timezone('America/Lima')->format('d/m/Y H:i') }} (Lima) por {{ $version->sender->name }}.</p>@endif
                    @if ($version->path)
                        <a href="{{ route('commercial.deliverables.download', [$deliverable, $version->number]) }}">Descargar {{ $version->original_name }} · versión {{ $version->number }}</a>
                    @else<p>No hay archivo adjunto en este borrador.</p>@endif
                    @if ($version->internal_note)<p class="mt-3"><strong>Nota interna:</strong> <span class="preserve-lines long-form-text">{{ $version->internal_note }}</span></p>@endif
                    @if ($version->decision)
                        <div class="next-action mt-3">
                            @include('shared.status-label', ['value' => $version->decision])
                            <p>Respuesta del cliente verificado · {{ $version->responded_at->timezone('America/Lima')->format('d/m/Y H:i') }} (Lima).</p>
                            @if ($version->comment)<p class="preserve-lines long-form-text mb-0">{{ $version->comment }}</p>@endif
                        </div>
                    @endif
                </article>
            @endforeach
        </section>
    </div></div>
</section>
@endsection

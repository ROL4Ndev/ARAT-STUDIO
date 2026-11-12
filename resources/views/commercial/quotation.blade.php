@extends('layouts.app')

@section('title', 'Cotización '.$quotation->code.' | ARAT STUDIO')

@section('content')
    @php($isCurrent = $quotation->serviceRequest->current_quotation_id === $quotation->id)
    <section class="workspace-view" aria-labelledby="quotation-title">
        <header class="workspace-hero">
            <div class="container">
                <h1 id="quotation-title">{{ $quotation->code }}</h1>
                <p>
                    Versión {{ $quotation->version }}
                    · @include('shared.status-label', ['value' => $quotation->status])
                    · {{ $isCurrent ? 'Versión actual' : 'Versión anterior o en preparación' }}
                </p>
                <a href="{{ route('commercial.requests.show', $quotation->serviceRequest) }}">Volver al expediente</a>
            </div>
        </header>

        <div class="workspace-content">
            <div class="container">
                @include('shared.errors')
                <article class="workspace-card">
                    <h2>Importes y estados técnicos</h2>
                    <p>Estimación referencial del expediente:
                        {{ $quotation->serviceRequest->estimated_cost === null ? 'Evaluación personalizada, sin importe automático' : 'PEN '.$quotation->serviceRequest->estimated_cost }}.
                    </p>
                    <p>Importe oficial de esta versión: <strong>PEN {{ $quotation->official_amount }}</strong></p>
                    <dl>
                        <dt>Estado de emisión PDF</dt>
                        <dd>@include('shared.status-label', ['value' => $quotation->pdf_status])</dd>
                        <dt>Estado de notificación</dt>
                        <dd>@include('shared.status-label', ['value' => $quotation->notification_status])</dd>
                    </dl>

                    @if ($quotation->status === 'borrador')
                        <h2>Editar borrador</h2>
                        <form method="POST" action="{{ route('commercial.quotations.update', $quotation) }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="request_lock_version" value="{{ $quotation->serviceRequest->lock_version }}">
                            <input type="hidden" name="lock_version" value="{{ $quotation->lock_version }}">
                            @include('commercial.quotation-fields', ['draft' => $quotation])
                            <button class="btn btn-outline-dark" type="submit" data-loading-label="Guardando cambios…">Guardar cambios del borrador</button>
                        </form>
                    @endif

                    @if (in_array($quotation->status, ['borrador', 'preparando'], true))
                        <form method="POST" action="{{ route('commercial.quotations.prepare', $quotation) }}" class="mt-4" data-confirm="La preparación congela el contenido de esta versión. La versión anterior no se sustituirá hasta que el PDF se emita. ¿Confirmas continuar?">
                            @csrf
                            <input type="hidden" name="lock_version" value="{{ $quotation->lock_version }}">
                            <button class="btn btn-dark" type="submit" data-loading-label="Preparando PDF…">
                                {{ $quotation->pdf_status === 'failed' ? 'Reintentar PDF de la misma versión' : 'Preparar emisión PDF' }}
                            </button>
                        </form>
                        <p class="mt-3">El servidor, el worker y el scheduler deben estar iniciados. Recarga esta página para consultar el resultado; no se hacen consultas automáticas.</p>
                    @endif

                    @if ($quotation->issued_at)
                        <h2>Vigencia</h2>
                        <p>
                            Emisión {{ $quotation->issued_at->timezone('America/Lima')->format('d/m/Y H:i:s') }}
                            · Vencimiento {{ $quotation->expires_at->timezone('America/Lima')->format('d/m/Y H:i:s') }} (Lima)
                            · @include('shared.status-label', ['value' => $quotation->isExpired() ? 'vencida' : 'vigente'])
                        </p>
                    @endif

                    @if ($quotation->pdf_status === 'ready' && $quotation->issued_at)
                        <a class="btn btn-dark" href="{{ route('commercial.quotations.download', $quotation) }}">Descargar PDF inmutable de esta versión</a>
                    @endif

                    <h2 class="mt-4">Contenido de la versión</h2>
                    <dl>
                        @foreach (['scope' => 'Alcance', 'deliverables' => 'Entregables', 'exclusions' => 'Exclusiones', 'execution_term' => 'Plazo', 'adjustment_reason' => 'Motivo del ajuste', 'version_reason' => 'Motivo de la versión'] as $field => $title)
                            <dt>{{ $title }}</dt>
                            <dd class="preserve-lines long-form-text">{{ $quotation->$field ?? 'Dato heredado no registrado' }}</dd>
                        @endforeach
                    </dl>

                    @if ($intent)
                        <h2>Trabajo técnico</h2>
                        <p>
                            ID {{ $intent->id }}
                            · @include('shared.status-label', ['value' => $intent->status])
                            · {{ $intent->attempts }} intentos
                            @if ($intent->error)
                                · {{ $intent->error }}
                            @endif
                        </p>
                    @endif
                    @if ($quotation->technical_error)
                        <p class="alert alert-warning" role="status">{{ $quotation->technical_error }}</p>
                    @endif

                    <h2>Notificaciones</h2>
                    <ul>
                        @forelse ($notices as $notice)
                            <li>
                                @include('shared.status-label', ['value' => $notice->status])
                                · {{ $notice->attempts }} intentos
                                @if ($notice->error)
                                    <p class="form-feedback">{{ $notice->error }}</p>
                                @endif
                                <details>
                                    <summary>Consultar intentos de notificación</summary>
                                    <ul>
                                        @forelse ($notice->deliveries as $delivery)
                                            <li>
                                                Intento {{ $delivery->attempt_no }}
                                                · {{ $delivery->attempted_at->timezone('America/Lima')->format('d/m/Y H:i:s') }} (Lima)
                                                · {{ $delivery->destination }}
                                                · @include('shared.status-label', ['value' => $delivery->status])
                                                · {{ $delivery->provider_reference ?? 'Sin referencia de proveedor' }}
                                                @if ($delivery->error)
                                                    · {{ $delivery->error }}
                                                @endif
                                            </li>
                                        @empty
                                            <li>No hay intentos nuevos; se conserva el agregado anterior.</li>
                                        @endforelse
                                    </ul>
                                </details>
                                @if ($notice->status === 'failed')
                                    <form method="POST" action="{{ route('commercial.notices.retry', $notice->id) }}" data-confirm="Se preparará el reintento autorizado de esta notificación. ¿Deseas continuar?">
                                        @csrf
                                        <input type="hidden" name="lock_version" value="{{ $quotation->lock_version }}">
                                        <button class="btn btn-outline-dark mt-2" type="submit" data-loading-label="Preparando reintento…">Preparar reintento de notificación</button>
                                    </form>
                                @endif
                            </li>
                        @empty
                            <li>Pendientes de la emisión.</li>
                        @endforelse
                    </ul>
                    <p>Una notificación simulada corresponde al transporte local log/array. “Enviada al transporte” indica aceptación por este, no confirma la entrega en la bandeja del destinatario.</p>
                </article>
            </div>
        </div>
    </section>
@endsection

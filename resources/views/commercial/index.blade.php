@extends('layouts.app')

@section('title', 'Gestión comercial | ARAT STUDIO')

@section('content')
    <section class="workspace-view" aria-labelledby="management-title">
        <header class="workspace-hero">
            <div class="container">
                <h1 id="management-title">Gestión comercial</h1>
                <p>{{ auth()->user()->role === 'admin' ? 'Vista de todos los expedientes.' : 'Vista de tus expedientes asignados.' }}</p>
            </div>
        </header>

        <div class="workspace-content">
            <div class="container">
                @include('shared.errors')

                <section class="workspace-card mb-4" aria-labelledby="metrics-title">
                    <h2 id="metrics-title">Indicadores del período</h2>
                    <dl class="row">
                        <dt class="col-sm-8">Pendientes visibles</dt>
                        <dd class="col-sm-4">{{ $counts['pendiente'] ?? 0 }}</dd>
                        <dt class="col-sm-8">Promedio hasta primera evaluación</dt>
                        <dd class="col-sm-4">{{ $minutes === null ? 'Sin datos' : round($minutes, 1).' minutos' }}</dd>
                        <dt class="col-sm-8">Cotizaciones emitidas</dt>
                        <dd class="col-sm-4">{{ $issued }}</dd>
                        <dt class="col-sm-8">Tasa de aceptación</dt>
                        <dd class="col-sm-4">{{ $denominator ? round(100 * $accepted / $denominator, 1).'%' : 'Sin emisiones' }}</dd>
                    </dl>
                    <p class="form-text mb-0">La aceptación se calcula sobre solicitudes distintas cuya primera emisión pertenece al período, por defecto los últimos 30 días. El promedio de evaluación considera solicitudes creadas en el período. Se aplica tu visibilidad por asignación.</p>
                </section>

                <section class="workspace-card mb-4" aria-labelledby="requests-title">
                    <h2 id="requests-title">Expedientes visibles</h2>
                    <p>Los datos de contacto aparecen solo en expedientes dentro de tu ámbito autorizado. La bandeja sin asignar, más abajo, muestra información mínima.</p>

                    <form method="GET" class="row g-3 mb-4" aria-label="Filtros de expedientes">
                        <div class="col-md-4">
                            <label for="search" class="form-label">Código, nombre o correo</label>
                            <input id="search" name="search" class="form-control" maxlength="150" value="{{ request('search') }}">
                        </div>
                        <div class="col-md-2">
                            <label for="status" class="form-label">Estado</label>
                            <select id="status" name="status" class="form-select">
                                <option value="">Todos</option>
                                @foreach (['pendiente' => 'Pendiente', 'evaluacion' => 'En evaluación', 'observada' => 'Observada', 'aprobado' => 'Aprobada', 'rechazado' => 'Rechazada', 'cancelado' => 'Cancelada', 'cerrada' => 'Cerrada'] as $state => $label)
                                    <option value="{{ $state }}" @selected(request('status') === $state)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="from" class="form-label">Desde (Lima)</label>
                            <input id="from" name="from" type="date" class="form-control" value="{{ request('from') }}">
                        </div>
                        <div class="col-md-2">
                            <label for="to" class="form-label">Hasta (Lima)</label>
                            <input id="to" name="to" type="date" class="form-control" value="{{ request('to') }}">
                        </div>
                        <div class="col-md-3">
                            <label for="service_id" class="form-label">Servicio histórico</label>
                            <select id="service_id" name="service_id" class="form-select">
                                <option value="">Todos</option>
                                @foreach ($services as $service)
                                    <option value="{{ $service->id }}" @selected((string) request('service_id') === (string) $service->id)>{{ $service->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="history_action" class="form-label">Acción del historial</label>
                            <input id="history_action" name="history_action" class="form-control" maxlength="60" value="{{ request('history_action') }}">
                        </div>
                        <div class="col-md-2 align-self-end">
                            <button class="btn btn-dark" type="submit">Aplicar filtros</button>
                        </div>
                        <div class="col-md-2 align-self-end">
                            <a class="btn btn-outline-dark" href="{{ route('management.dashboard') }}">Limpiar filtros</a>
                        </div>
                    </form>

                    <div class="table-responsive" role="region" aria-label="Expedientes visibles" tabindex="0">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th scope="col">Código</th>
                                    <th scope="col">Cliente</th>
                                    <th scope="col">Estado</th>
                                    <th scope="col">Responsable</th>
                                    <th scope="col">Cotización vigente</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($requests as $requestItem)
                                    <tr>
                                        <th scope="row">
                                            <a href="{{ route('commercial.requests.show', $requestItem) }}">Abrir expediente {{ $requestItem->code }}</a>
                                        </th>
                                        <td>{{ $requestItem->client_name }}<br><span class="text-break">{{ $requestItem->client_email }}</span></td>
                                        <td>@include('shared.status-label', ['value' => $requestItem->status])</td>
                                        <td>{{ $requestItem->assignee?->name ?? 'Sin asignar' }}</td>
                                        <td>{{ $requestItem->currentQuotation?->code ?? 'Sin cotización vigente' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5">No hay expedientes visibles con estos filtros.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    {{ $requests->links() }}
                </section>

                <section class="workspace-card mb-4" aria-labelledby="unassigned-title">
                    <h2 id="unassigned-title">Bandeja mínima sin asignar</h2>
                    <p>No incluye contactos ni enlaces de descarga. Tomar una solicitud se confirma con el estado actual del expediente.</p>
                    <ul class="list-unstyled mb-0">
                        @forelse ($unassigned as $unassignedRequest)
                            <li class="border-top py-3">
                                <p>
                                    <strong>{{ $unassignedRequest->code }}</strong>
                                    · {{ $unassignedRequest->service }}
                                    · {{ $unassignedRequest->project_type }}
                                    · @include('shared.status-label', ['value' => $unassignedRequest->status])
                                    · {{ $unassignedRequest->created_at->timezone('America/Lima')->format('d/m/Y H:i') }} (Lima)
                                </p>
                                @if (auth()->user()->role === 'gestor')
                                    <form method="POST" action="{{ route('commercial.requests.transition', $unassignedRequest->code) }}" data-confirm="¿Confirmas tomar esta solicitud? La asignación se validará con el estado actual del expediente.">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="lock_version" value="{{ $unassignedRequest->lock_version }}">
                                        <input type="hidden" name="action" value="claim">
                                        <label for="claim-reason-{{ $unassignedRequest->code }}" class="form-label">Motivo para tomar la solicitud <span aria-hidden="true">*</span></label>
                                        <textarea id="claim-reason-{{ $unassignedRequest->code }}" name="reason" required minlength="5" maxlength="2000" class="form-control"></textarea>
                                        <button class="btn btn-outline-dark mt-2" type="submit" data-loading-label="Tomando solicitud…">Tomar solicitud</button>
                                    </form>
                                @else
                                    <a href="{{ route('commercial.requests.show', $unassignedRequest->code) }}">Abrir para asignar</a>
                                @endif
                            </li>
                        @empty
                            <li class="border-top py-3">No hay solicitudes sin asignar.</li>
                        @endforelse
                    </ul>
                    {{ $unassigned->links() }}
                </section>

                <section class="workspace-card" aria-labelledby="history-title">
                    <h2 id="history-title">Historial filtrado</h2>
                    <ol>
                        @forelse ($histories as $history)
                            <li>
                                <a href="{{ route('commercial.requests.show', $history->serviceRequest) }}">{{ $history->serviceRequest->code }}</a>
                                · {{ $history->created_at->timezone('America/Lima')->format('d/m/Y H:i:s') }} (Lima)
                                · {{ ucfirst(str_replace('_', ' ', $history->action)) }} · {{ $history->observation }}
                            </li>
                        @empty
                            <li>Sin eventos visibles para estos filtros.</li>
                        @endforelse
                    </ol>
                    {{ $histories->links() }}
                </section>
            </div>
        </div>
    </section>
@endsection

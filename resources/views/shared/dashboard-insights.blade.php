@php($period = $overview['period'])
<section class="dashboard-insights mb-4" aria-labelledby="insights-title">
    <header class="insights-heading">
        <div>
            <p class="section-label">El pulso del estudio</p>
            <h2 id="insights-title">Solicitudes y atención</h2>
        </div>
        <p class="form-text">{{ $period['from']->format('d/m/Y') }}–{{ $period['to']->format('d/m/Y') }} · America/Lima
        </p>
    </header>
    <p class="form-text">
        {{ auth()->user()->role === 'admin' ? 'Vista general autorizada.' : 'Solo expedientes asignados a tu cuenta.' }}
        Los gráficos cuentan solicitudes recibidas en este período y su estado actual. No representan ingresos cobrados.
        @if ($overview['filtered'])
            Los filtros de la bandeja también se aplican a este resumen y a sus enlaces.
        @endif
    </p>
    <dl class="metrics-grid insights-totals">
        <div class="metric">
            <dt>Recibidas en el período</dt>
            <dd>{{ $overview['received'] }}</dd>
        </div>
        <div class="metric">
            <dt>Pendientes de atención · {{ $overview['filtered'] ? 'según filtros' : 'todos los períodos' }}</dt>
            <dd>{{ $overview['attentionCount'] }}</dd>
        </div>
    </dl>
    <div class="insights-charts" data-dashboard-charts>
        <article class="workspace-card">
            <h3>Solicitudes recibidas por semana</h3>
            <p class="form-text">Semanas de lunes a domingo; los extremos se recortan al período. Hasta 52 intervalos
                recientes.</p>
            @if ($overview['received'])
                <div class="chart-frame" hidden>
                    <canvas data-weekly-chart role="img"
                        aria-label="Solicitudes por semana; los valores están en la tabla siguiente">
                    </canvas>
                </div>
            @else
                <p class="empty-state">Todavía no hay solicitudes recibidas en este período.</p>
            @endif
            <details class="secondary-details" open>
                <summary>Datos de solicitudes por semana</summary>
                <table class="table table-sm">
                    <caption class="visually-hidden">Solicitudes recibidas por semana, hora de Lima</caption>
                    <thead>
                        <tr>
                            <th scope="col">Intervalo</th>
                            <th scope="col">Solicitudes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($overview['weeks'] as $week)
                            <tr>
                                <th scope="row">{{ $week['label'] }}</th>
                                <td>{{ $week['count'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </details>
        </article>
        <article class="workspace-card">
            <h3>Distribución por estado actual</h3>
            <p class="form-text">Estado actual de las solicitudes creadas dentro del período seleccionado.</p>
            @if ($overview['received'])
                <div class="chart-frame" hidden>
                    <canvas data-status-chart role="img"
                        aria-label="Distribución de solicitudes por estado; valores en la tabla siguiente">
                    </canvas>
                </div>
            @endif
            <details class="secondary-details" open>
                <summary>Datos de solicitudes por estado</summary>
                <table class="table table-sm">
                    <caption class="visually-hidden">Distribución por estado actual</caption>
                    <thead>
                        <tr>
                            <th scope="col">Estado</th>
                            <th scope="col">Solicitudes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($overview['states'] as $state)
                            <tr>
                                <th scope="row">{{ $state['label'] }}</th>
                                <td>{{ $state['count'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </details>
        </article>
    </div>
    <div class="insights-lists">
        <section class="workspace-card" aria-labelledby="attention-title">
            <h3 id="attention-title">Lo que necesita atención</h3>
            <p class="form-text">Las cinco solicitudes más antiguas pendientes, en evaluación u observadas.
                {{ $overview['filtered'] ? 'Se aplican los filtros de la bandeja.' : 'Incluye todos los períodos.' }}
            </p>
            <ul class="request-summary-list">
                @forelse($overview['attentionRequests'] as $item)
                    <li>
                        <a href="{{ route('commercial.requests.show', $item) }}">{{ $item->code }}</a>
                        <span>{{ $item->service }}</span>
                        @include('shared.status-label', ['value' => $item->status])
                    </li>
                @empty
                    <li class="empty-state">No tienes solicitudes pendientes de atención.</li>
                @endforelse
            </ul>
        </section>
        <section class="workspace-card" aria-labelledby="recent-title">
            <h3 id="recent-title">Solicitudes recientes</h3>
            <p class="form-text">Las seis últimas dentro de tu ámbito autorizado.
                {{ $overview['filtered'] ? 'Se aplican los filtros de la bandeja.' : 'Incluye todos los períodos.' }}
            </p>
            <ul class="request-summary-list">
                @forelse($overview['recentRequests'] as $item)
                    <li>
                        <a href="{{ route('commercial.requests.show', $item) }}">{{ $item->code }}</a>
                        <span>{{ $item->created_at->timezone('America/Lima')->format('d/m/Y H:i') }}
                            · Lima</span>
                        @include('shared.status-label', ['value' => $item->status])
                    </li>
                @empty
                    <li class="empty-state">Cuando se
                        reciban solicitudes, aparecerán aquí.</li>
                @endforelse
            </ul>
        </section>
    </div>
    <script type="application/json" id="dashboard-chart-data">{!! json_encode(['weeks' => $overview['weeks'], 'states' => $overview['states']], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
</section>
@push('scripts')
    @vite('resources/js/dashboard.js')
@endpush

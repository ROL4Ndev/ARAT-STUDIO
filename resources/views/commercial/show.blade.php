@extends('layouts.app')

@section('title', 'Solicitud '.$serviceRequest->code.' | ARAT STUDIO')

@section('content')
    <section class="workspace-view" aria-labelledby="request-code">
        <header class="workspace-hero">
            <div class="container">
                <a href="{{ route('management.dashboard') }}">Volver a Gestión comercial</a>
                <h1 id="request-code">{{ $serviceRequest->code }}</h1>
                <p>
                    @include('shared.status-label', ['value' => $serviceRequest->status])
                    · Servicio: {{ $serviceRequest->service }}
                    · Responsable: {{ $serviceRequest->assignee?->name ?? 'Sin asignar' }}
                </p>
                <nav class="d-flex flex-wrap gap-3" aria-label="Secciones del expediente">
                    <a href="#resumen">Resumen</a>
                    <a href="#evaluacion">Evaluación</a>
                    <a href="#archivos">Archivos</a>
                    <a href="#cotizaciones">Cotizaciones</a>
                    <a href="#historial">Historial</a>
                </nav>
            </div>
        </header>

        <div class="workspace-content">
            <div class="container">
                @include('shared.errors')

                <section class="workspace-card mb-4" id="resumen" aria-labelledby="summary-title">
                    <h2 id="summary-title">Resumen</h2>
                    <dl class="row">
                        <dt class="col-md-3">Código</dt>
                        <dd class="col-md-9">{{ $serviceRequest->code }}</dd>
                        <dt class="col-md-3">Estado</dt>
                        <dd class="col-md-9">@include('shared.status-label', ['value' => $serviceRequest->status])</dd>
                        <dt class="col-md-3">Servicio histórico</dt>
                        <dd class="col-md-9">{{ $serviceRequest->service }}</dd>
                        <dt class="col-md-3">Responsable</dt>
                        <dd class="col-md-9">{{ $serviceRequest->assignee?->name ?? 'Sin asignar' }}</dd>
                        <dt class="col-md-3">Creada</dt>
                        <dd class="col-md-9">{{ $serviceRequest->created_at->timezone('America/Lima')->format('d/m/Y H:i:s') }} (Lima)</dd>
                        <dt class="col-md-3">Última actualización</dt>
                        <dd class="col-md-9">{{ $serviceRequest->updated_at->timezone('America/Lima')->format('d/m/Y H:i:s') }} (Lima)</dd>
                    </dl>

                    <h3>Datos de la solicitud</h3>
                    <dl class="row">
                        @foreach (['client_name' => 'Cliente', 'client_email' => 'Correo', 'client_phone' => 'Teléfono', 'city' => 'Ciudad', 'project_type' => 'Tipo de proyecto', 'description' => 'Descripción', 'area_m2' => 'Área m²', 'lod_selected' => 'LOD'] as $field => $label)
                            <dt class="col-md-3">{{ $label }}</dt>
                            <dd class="col-md-9 text-break {{ $field === 'description' ? 'preserve-lines long-form-text' : '' }}">{{ $serviceRequest->$field ?? 'No aplica' }}</dd>
                        @endforeach
                    </dl>

                    <h3>Estimación registrada</h3>
                    <p>Importe referencial: {{ $serviceRequest->estimated_cost === null ? 'Evaluación personalizada, sin importe automático' : 'PEN '.$serviceRequest->estimated_cost }} · {{ $serviceRequest->estimation_reason }}</p>
                    <p>Tarifa histórica: {{ $serviceRequest->base_price_m2 ?? 'No aplica' }} × {{ $serviceRequest->lod_factor ?? 'No aplica' }}; límite {{ $serviceRequest->max_area_m2 ?? 'No aplica' }} m².</p>
                    <p>Revisión actual: {{ $serviceRequest->currentRevision?->version ?? 'No registrada' }} · Consentimiento: {{ $serviceRequest->consented_at?->timezone('America/Lima')->format('d/m/Y H:i:s').' (Lima)' ?? 'No registrado' }}</p>

                    @if (auth()->user()->role === 'admin' && ! in_array($serviceRequest->status, ['cerrada', 'cancelado', 'rechazado'], true))
                        <h3>Asignación</h3>
                        <form method="POST" action="{{ route('commercial.requests.transition', $serviceRequest) }}" data-confirm="¿Confirmas guardar la asignación o reasignación?">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="action" value="assign">
                            <input type="hidden" name="lock_version" value="{{ $serviceRequest->lock_version }}">
                            <label for="assigned_to" class="form-label">Asignar o reasignar responsable</label>
                            <select name="assigned_to" id="assigned_to" class="form-select">
                                @foreach ($responsibles as $responsible)
                                    <option value="{{ $responsible->id }}" @selected($serviceRequest->assigned_to === $responsible->id)>{{ $responsible->name }} ({{ $responsible->role }})</option>
                                @endforeach
                            </select>
                            <label for="assign_reason" class="form-label mt-3">Motivo <span aria-hidden="true">*</span></label>
                            <textarea id="assign_reason" name="reason" class="form-control" required minlength="5" maxlength="2000"></textarea>
                            <button class="btn btn-dark mt-3" type="submit" data-loading-label="Guardando asignación…">Confirmar asignación</button>
                        </form>
                    @endif
                </section>

                <section class="workspace-card mb-4" id="evaluacion" aria-labelledby="evaluation-title">
                    <h2 id="evaluation-title">Evaluación</h2>
                    @php($actions = match ($serviceRequest->status) {
                        'pendiente' => ['start' => 'Iniciar evaluación'],
                        'evaluacion' => ['observe' => 'Observar para corrección', 'approve' => 'Aprobar con evaluación técnica', 'reject' => 'Rechazar'],
                        'observada' => [],
                        'aprobado' => ['close' => 'Cerrar sin acuerdo'],
                        default => [],
                    })

                    @if ($serviceRequest->status === 'pendiente')
                        <p class="next-action"><strong>Siguiente acción disponible:</strong> iniciar la evaluación del expediente.</p>
                    @elseif ($serviceRequest->status === 'evaluacion')
                        <p class="next-action"><strong>Siguiente acción disponible:</strong> registrar una observación pública, una evaluación técnica para aprobar o un rechazo.</p>
                    @elseif ($serviceRequest->status === 'observada')
                        <p class="next-action"><strong>Siguiente paso:</strong> registrar una corrección validada del cliente; la solicitud volverá a evaluación y se avisará al responsable.</p>
                    @elseif ($serviceRequest->status === 'aprobado')
                        <p class="next-action"><strong>Siguiente acción disponible:</strong> preparar una cotización o cerrar sin acuerdo.</p>
                    @else
                        <p class="next-action">Este expediente no admite más cambios de evaluación. Puedes consultar su información e historial.</p>
                    @endif

                    @include('commercial.correction-form')

                    @foreach ($actions as $action => $label)
                        <form
                            class="border p-3 mb-3"
                            method="POST"
                            action="{{ route('commercial.requests.transition', $serviceRequest) }}"
                            @if (in_array($action, ['observe', 'approve', 'reject', 'close'], true))
                                data-confirm="{{ $action === 'observe' ? 'La observación será visible para el cliente. ¿Confirmas continuar?' : '¿Confirmas esta acción para el expediente?' }}"
                            @endif
                        >
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="action" value="{{ $action }}">
                            <input type="hidden" name="lock_version" value="{{ $serviceRequest->lock_version }}">
                            <h3 class="h5">{{ $label }}</h3>
                            <label for="reason-{{ $action }}" class="form-label">
                                Motivo {{ $action === 'observe' ? '(interno)' : '' }} <span aria-hidden="true">*</span>
                            </label>
                            <textarea id="reason-{{ $action }}" name="reason" class="form-control" required minlength="5" maxlength="5000">{{ old('reason') }}</textarea>

                            @if ($action === 'observe')
                                <label for="public_observation" class="form-label mt-3">Observación pública para el cliente <span aria-hidden="true">*</span></label>
                                <textarea id="public_observation" name="public_observation" class="form-control" required minlength="10" maxlength="5000">{{ old('public_observation') }}</textarea>
                                <p class="form-text">Este texto se comparte con el cliente; el motivo anterior es interno.</p>
                            @endif

                            @if ($action === 'approve')
                                <label for="assessment" class="form-label mt-3">Evaluación técnica interna <span aria-hidden="true">*</span></label>
                                <textarea id="assessment" name="assessment" class="form-control long-form-text" required minlength="20" maxlength="10000">{{ old('assessment') }}</textarea>
                                <p class="form-text">Esta evaluación es interna y no se muestra en el expediente del cliente.</p>
                            @endif

                            <button class="btn btn-dark mt-3" type="submit" data-loading-label="Registrando…">{{ $label }}</button>
                        </form>
                    @endforeach

                    @if (in_array($serviceRequest->status, ['pendiente', 'evaluacion', 'observada', 'aprobado'], true))
                        <form method="POST" action="{{ route('commercial.requests.transition', $serviceRequest) }}" data-confirm="¿Confirmas cancelar la solicitud? Se conservarán los datos y archivos; no se podrá deshacer esta acción.">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="lock_version" value="{{ $serviceRequest->lock_version }}">
                            <p>Cancelar anula borradores y propuestas abiertas; conserva todos los datos y archivos. Una aceptación impide esta acción.</p>
                            <label for="cancel_reason" class="form-label">Motivo de cancelación <span aria-hidden="true">*</span></label>
                            <textarea name="reason" id="cancel_reason" class="form-control" required minlength="5" maxlength="2000"></textarea>
                            <button class="btn btn-outline-dark mt-3" type="submit" data-loading-label="Cancelando…">Cancelar solicitud</button>
                        </form>
                    @endif

                    @foreach ($serviceRequest->evaluations as $evaluation)
                        <article class="border-top mt-4 pt-3">
                            <h3 class="h5">Evaluación técnica de {{ $evaluation->user->name }}</h3>
                            <p class="long-form-text preserve-lines">{{ $evaluation->assessment }}</p>
                        </article>
                    @endforeach
                </section>

                <section class="workspace-card mb-4" id="archivos" aria-labelledby="files-title">
                    <h2 id="files-title">Archivos</h2>
                    <ul>
                        @forelse ($serviceRequest->files as $file)
                            <li>
                                {{ $file->original_name }} · revisión conservada ·
                                <a href="{{ route('commercial.files.download', $file->id) }}">Descargar archivo</a>
                                @if (in_array(strtolower(pathinfo($file->path, PATHINFO_EXTENSION)), ['pdf', 'png', 'jpg', 'jpeg'], true))
                                    · <a href="{{ route('commercial.files.download', [$file->id, 'preview' => 1]) }}" target="_blank" rel="noopener noreferrer">Previsualizar en otra pestaña</a>
                                @endif
                            </li>
                        @empty
                            <li>No hay archivos adjuntos.</li>
                        @endforelse
                    </ul>
                    @if ($serviceRequest->external_url)
                        <a href="{{ $serviceRequest->external_url }}" target="_blank" rel="noopener noreferrer">Abrir enlace externo en otra pestaña</a>
                    @endif
                </section>

                <section id="cotizaciones" aria-label="Cotizaciones">
                    @include('commercial.quotation-form')
                </section>

                <section class="workspace-card mb-4" aria-labelledby="notifications-title">
                    <h2 id="notifications-title">Notificaciones del expediente</h2>
                    <ul>
                        @forelse ($notices as $notice)
                            <li class="mb-3">
                                {{ $notice->kind }} · @include('shared.status-label', ['value' => $notice->status])
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
                                            <li>No hay intentos nuevos registrados; se conserva el agregado anterior.</li>
                                        @endforelse
                                    </ul>
                                </details>
                                @if ($notice->status === 'failed')
                                    <form method="POST" action="{{ route('commercial.notices.retry', $notice->id) }}" data-confirm="Se preparará el reintento autorizado de esta notificación. ¿Deseas continuar?">
                                        @csrf
                                        <input name="lock_version" type="hidden" value="{{ $serviceRequest->lock_version }}">
                                        <button class="btn btn-outline-dark mt-2" type="submit" data-loading-label="Preparando reintento…">Preparar reintento de notificación</button>
                                    </form>
                                @endif
                            </li>
                        @empty
                            <li>No hay notificaciones registradas en este expediente heredado.</li>
                        @endforelse
                    </ul>
                    @if ($serviceRequest->closed_at)
                        <h3>Resultado de cierre</h3>
                        <p>{{ $serviceRequest->closed_at->timezone('America/Lima')->format('d/m/Y H:i:s') }} (Lima) · {{ $serviceRequest->closure_reason }}</p>
                    @endif
                </section>

                <section class="workspace-card" id="historial" aria-labelledby="history-title">
                    <h2 id="history-title">Historial cronológico</h2>
                    <ol>
                        @forelse ($serviceRequest->histories as $history)
                            <li class="mb-3">
                                <time datetime="{{ $history->created_at->toIso8601String() }}">{{ $history->created_at->timezone('America/Lima')->format('d/m/Y H:i:s') }} (Lima)</time>
                                · {{ $history->user?->name ?? 'Cliente / sistema' }}
                                · {{ ucfirst(str_replace('_', ' ', $history->action)) }}
                                · @include('shared.status-label', ['value' => $history->previous_status ?? 'Inicio'])
                                → @include('shared.status-label', ['value' => $history->new_status])
                                · {{ $history->observation }}
                                · <strong>{{ $history->is_public ? 'Visible para el cliente' : 'Nota interna' }}</strong>
                            </li>
                        @empty
                            <li>No hay eventos registrados.</li>
                        @endforelse
                    </ol>
                </section>
            </div>
        </div>
    </section>
@endsection

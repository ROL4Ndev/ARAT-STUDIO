@extends('layouts.app')

@section('title', 'Mi expediente '.$dossier->code.' | ARAT STUDIO')

@section('content')
    <section class="workspace-view" aria-labelledby="dossier-title">
        <header class="workspace-hero">
            <div class="container">
                <h1 id="dossier-title">{{ $dossier->code }}</h1>
                <p>
                    Estado: @include('shared.status-label', ['value' => $dossier->status])
                    · Acceso válido hasta {{ $grant->expires_at->timezone('America/Lima')->format('d/m/Y H:i:s') }} (Lima).
                    Solicita un nuevo enlace desde
                    <a href="{{ route('client.access') }}">Acceder a mi expediente</a>.
                </p>
                <form method="POST" action="{{ route('client.logout') }}">
                    @csrf
                    <button class="btn btn-outline-light" type="submit">Cerrar acceso verificado</button>
                </form>
            </div>
        </header>

        <div class="workspace-content">
            <div class="container">
                @include('shared.errors')

                @if (in_array($dossier->status, ['rechazado', 'cancelado', 'cerrada'], true))
                    <section class="alert alert-info" role="status" aria-labelledby="outcome-title">
                        <h2 id="outcome-title" class="h4">Resultado de la solicitud</h2>
                        @if ($dossier->quotations->contains('status', 'aceptada'))
                            <p class="mb-0">La propuesta fue aceptada. Esta respuesta registrada en el sistema no constituye una firma digital certificada.</p>
                        @else
                            <p class="mb-0">La solicitud está cerrada: @include('shared.status-label', ['value' => $dossier->status]). Puedes consultar el historial y los documentos conservados.</p>
                        @endif
                    </section>
                @endif

                <section class="workspace-card mb-4" aria-labelledby="dossier-summary-title">
                    <h2 id="dossier-summary-title">Resumen de tu solicitud</h2>
                    <dl class="row">
                        <dt class="col-md-3">Nombre</dt>
                        <dd class="col-md-9">{{ $dossier->client_name }}</dd>
                        <dt class="col-md-3">Correo</dt>
                        <dd class="col-md-9 text-break">{{ $dossier->client_email }}</dd>
                        <dt class="col-md-3">Ciudad</dt>
                        <dd class="col-md-9">{{ $dossier->city }}</dd>
                        <dt class="col-md-3">Servicio</dt>
                        <dd class="col-md-9">{{ $dossier->service }} · {{ $dossier->project_type }}</dd>
                    </dl>
                    <h3>Descripción</h3>
                    <p class="preserve-lines long-form-text">{{ $dossier->description }}</p>
                    <h3>Estimación</h3>
                    <p>Estimación referencial: {{ $dossier->estimated_cost === null ? 'Evaluación personalizada, sin importe automático' : 'PEN '.$dossier->estimated_cost }} · {{ $dossier->estimation_reason }}</p>
                    <h3>Archivos conservados</h3>
                    <ul>
                        @forelse ($dossier->files as $file)
                            <li><a href="{{ route('client.files.download', $file->id) }}">Descargar {{ $file->original_name }}</a></li>
                        @empty
                            <li>No hay archivos adjuntos.</li>
                        @endforelse
                    </ul>
                </section>

                @if ($dossier->status === 'observada')
                    <form method="POST" enctype="multipart/form-data" data-document-upload class="workspace-card mb-4" action="{{ route('client.correct') }}">
                        @csrf
                        <h2>Corregir solicitud observada</h2>
                        <input name="lock_version" type="hidden" value="{{ $dossier->lock_version }}">
                        <p>Servicio y correo no se cambian aquí. Los adjuntos anteriores se conservan; solo se agrega una revisión.</p>

                        @foreach (['client_name' => 'Nombre', 'client_phone' => 'Teléfono', 'city' => 'Ciudad'] as $field => $label)
                            <label for="{{ $field }}" class="form-label">{{ $label }} <span aria-hidden="true">*</span></label>
                            <input id="{{ $field }}" name="{{ $field }}" class="form-control mb-3" required value="{{ old($field, $dossier->$field) }}" autocomplete="{{ ['client_name' => 'name', 'client_phone' => 'tel', 'city' => 'address-level2'][$field] }}">
                            @error($field)
                                <p class="form-feedback" role="alert">{{ $message }}</p>
                            @enderror
                        @endforeach

                        <label for="project_type" class="form-label">Tipo de proyecto <span aria-hidden="true">*</span></label>
                        <select id="project_type" name="project_type" class="form-select mb-3">
                            @foreach (['Vivienda', 'Comercio', 'Oficina', 'Otro'] as $type)
                                <option @selected(old('project_type', $dossier->project_type) === $type)>{{ $type }}</option>
                            @endforeach
                        </select>

                        @if (($dossier->service_snapshot['estimation_mode'] ?? 'area_lod') === 'area_lod')
                            <label for="area_m2" class="form-label">Área (m²) <span aria-hidden="true">*</span></label>
                            <input name="area_m2" id="area_m2" class="form-control mb-3" type="number" step="0.01" min="0.01" max="1000000" required value="{{ old('area_m2', $dossier->area_m2) }}">
                            <label for="lod_selected" class="form-label">Nivel LOD <span aria-hidden="true">*</span></label>
                            <select name="lod_selected" id="lod_selected" class="form-select mb-3">
                                @foreach (['LOD_200', 'LOD_300', 'LOD_400'] as $lod)
                                    <option @selected(old('lod_selected', $dossier->lod_selected) === $lod)>{{ $lod }}</option>
                                @endforeach
                            </select>
                        @endif

                        <label for="description" class="form-label">Descripción corregida <span aria-hidden="true">*</span></label>
                        <textarea id="description" name="description" class="form-control mb-3" required minlength="10" maxlength="10000" rows="5">{{ old('description', $dossier->description) }}</textarea>
                        <label for="attachment" class="form-label">Nuevo adjunto opcional (máximo 50 MiB)</label>
                        <input id="attachment" name="attachment" type="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.dwg,.zip" aria-describedby="attachment-help file-selection file-error">
                        <p id="attachment-help" class="form-text">PDF, JPG, JPEG, PNG, DWG o ZIP. Si supera 50 MiB, utiliza un enlace externo.</p>
                        <p id="file-selection" class="file-selection" data-file-selection aria-live="polite"></p>
                        <p id="file-error" data-file-error class="form-feedback" role="alert" aria-live="assertive" tabindex="-1"></p>
                        <label for="external_url" class="form-label">Enlace externo http/https (opcional)</label>
                        <input id="external_url" name="external_url" type="url" class="form-control mb-3" value="{{ old('external_url', $dossier->external_url) }}" autocomplete="url">
                        <label for="correction_reason" class="form-label">Explicación de la corrección <span aria-hidden="true">*</span></label>
                        <textarea id="correction_reason" name="reason" class="form-control" required minlength="5" maxlength="5000">{{ old('reason') }}</textarea>
                        <button class="btn btn-dark mt-3" type="submit" data-loading-label="Registrando corrección…">Guardar revisión y conservar anteriores</button>
                    </form>
                @endif

                <section class="workspace-card mb-4" aria-labelledby="quotations-title">
                    <h2 id="quotations-title">Cotizaciones oficiales</h2>
                    @forelse ($dossier->quotations as $quotation)
                        @php($isCurrent = $dossier->current_quotation_id === $quotation->id)
                        <article class="border p-3 mb-3">
                            <h3>{{ $quotation->code }} · versión {{ $quotation->version }}</h3>
                            <p>
                                @include('shared.status-label', ['value' => $quotation->status])
                                · {{ $isCurrent ? 'Versión actual' : 'Versión anterior' }}
                                @if ($quotation->isExpired())
                                    · Vencida por fecha
                                @endif
                                · Importe oficial: <strong>PEN {{ $quotation->official_amount }}</strong>
                            </p>
                            <p>
                                Emisión: {{ $quotation->issued_at->timezone('America/Lima')->format('d/m/Y H:i:s') }}
                                · Vencimiento: {{ $quotation->expires_at->timezone('America/Lima')->format('d/m/Y H:i:s') }} (Lima)
                            </p>
                            <a class="btn btn-outline-dark" href="{{ route('client.quotations.download', $quotation) }}">Descargar PDF de la versión {{ $quotation->version }}</a>

                            <dl class="mt-3">
                                @foreach (['scope' => 'Alcance', 'deliverables' => 'Entregables', 'exclusions' => 'Exclusiones', 'execution_term' => 'Plazo de ejecución'] as $field => $label)
                                    <dt>{{ $label }}</dt>
                                    <dd class="preserve-lines long-form-text">{{ $quotation->$field ?? 'No registrado en esta versión' }}</dd>
                                @endforeach
                            </dl>

                            @if ($isCurrent && $quotation->status === 'emitida' && ! $quotation->isExpired() && $dossier->status === 'aprobado')
                                <p>Tu respuesta se confirma en el servidor; no equivale a una firma digital certificada.</p>
                                <form method="POST" action="{{ route('client.respond', $quotation) }}" class="mb-3" data-confirm="¿Confirmas aceptar la propuesta vigente por PEN {{ $quotation->official_amount }}? La respuesta se registrará en el expediente.">
                                    @csrf
                                    <input type="hidden" name="decision" value="aceptada">
                                    <input type="hidden" name="lock_version" value="{{ $quotation->lock_version }}">
                                    <button class="btn btn-dark" type="submit" data-loading-label="Registrando respuesta…">Aceptar propuesta vigente</button>
                                </form>
                                <form method="POST" action="{{ route('client.respond', $quotation) }}" data-confirm="¿Confirmas rechazar esta propuesta? La decisión se registrará en el expediente.">
                                    @csrf
                                    <input type="hidden" name="decision" value="rechazada">
                                    <input type="hidden" name="lock_version" value="{{ $quotation->lock_version }}">
                                    <label for="reject-{{ $quotation->version }}" class="form-label">Motivo del rechazo <span aria-hidden="true">*</span></label>
                                    <textarea id="reject-{{ $quotation->version }}" name="reason" class="form-control" required minlength="5" maxlength="5000">{{ old('reason') }}</textarea>
                                    <button class="btn btn-outline-dark mt-3" type="submit" data-loading-label="Registrando respuesta…">Rechazar propuesta</button>
                                </form>
                            @endif
                        </article>
                    @empty
                        <p>Todavía no hay cotizaciones emitidas disponibles.</p>
                    @endforelse
                </section>

                <section class="workspace-card mb-4" aria-labelledby="public-history-title">
                    <h2 id="public-history-title">Historial público</h2>
                    <ol>
                        @forelse ($dossier->histories as $history)
                            <li>
                                <time datetime="{{ $history->created_at->toIso8601String() }}">{{ $history->created_at->timezone('America/Lima')->format('d/m/Y H:i:s') }} (Lima)</time>
                                · {{ $history->observation }}
                            </li>
                        @empty
                            <li>No hay eventos públicos registrados.</li>
                        @endforelse
                    </ol>
                </section>

                @if (! in_array($dossier->status, ['rechazado', 'cancelado', 'cerrada'], true))
                    <section class="workspace-card mb-4" aria-labelledby="email-change-title">
                        <h2 id="email-change-title">Verificar otro correo</h2>
                        <form method="POST" action="{{ route('client.email') }}">
                            @csrf
                            <label for="new_email" class="form-label">Nuevo correo electrónico <span aria-hidden="true">*</span></label>
                            <input id="new_email" name="email" type="email" class="form-control" required maxlength="255" autocomplete="email" value="{{ old('email') }}">
                            <p class="form-text">El correo actual no cambia hasta confirmar el enlace desde esta sesión verificada.</p>
                            <button class="btn btn-outline-dark mt-3" type="submit" data-loading-label="Preparando verificación…">Preparar verificación</button>
                        </form>
                        <a class="d-inline-flex mt-3" href="{{ route('service-requests.create', ['related' => 1]) }}">Solicitar otro servicio en una nueva solicitud vinculada</a>
                    </section>
                @endif

                @if (in_array($dossier->status, ['pendiente', 'evaluacion', 'observada', 'aprobado'], true))
                    <form method="POST" class="workspace-card" action="{{ route('client.cancel') }}" data-confirm="¿Confirmas cancelar tu solicitud? La respuesta se registrará y no se podrá deshacer.">
                        @csrf
                        <input type="hidden" name="lock_version" value="{{ $dossier->lock_version }}">
                        <h2>Cancelar solicitud</h2>
                        <label for="cancel_reason" class="form-label">Motivo <span aria-hidden="true">*</span></label>
                        <textarea id="cancel_reason" name="reason" class="form-control" required minlength="5" maxlength="5000">{{ old('reason') }}</textarea>
                        <button class="btn btn-outline-dark mt-3" type="submit" data-loading-label="Registrando cancelación…">Confirmar cancelación</button>
                    </form>
                @endif
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('js/service-request.js') }}" defer></script>
@endpush

@extends('layouts.app')

@section('title', 'Solicitar proyecto | ARAT STUDIO')

@section('content')
    <section class="workspace-view" aria-labelledby="request-title">
        <header class="workspace-hero">
            <div class="container">
                <h1 id="request-title">Solicitar proyecto</h1>
                <p>La estimación es referencial. El equipo confirmará el alcance y el importe oficial.</p>
            </div>
        </header>

        <div class="workspace-content">
            <div class="container">
                <form
                    class="workspace-card"
                    id="project-request"
                    data-document-upload
                    data-estimate-endpoint="{{ route('quotation.calculate') }}"
                    action="{{ route('service-requests.store') }}"
                    method="POST"
                    enctype="multipart/form-data"
                >
                    @csrf
                    @include('shared.errors')

                    @if (isset($estimateConflict))
                        <div class="alert alert-warning" role="alert" aria-live="assertive">
                            {{ $estimateConflict }}
                            <strong>Revisa la estimación actualizada y vuelve a confirmar antes de enviar.</strong>
                            Si adjuntaste un archivo, el navegador no lo conserva al actualizar el formulario; vuelve a seleccionarlo.
                        </div>
                    @endif

                    <input type="hidden" name="submission_token" value="{{ old('submission_token', $token) }}">
                    <input type="hidden" name="estimate_token" id="estimate_token" value="{{ $estimate['estimate_token'] ?? old('estimate_token') }}">
                    @if (request('related'))
                        <input type="hidden" name="related" value="1">
                    @endif

                    <div class="row g-3">
                        @foreach (['client_name' => 'Nombre completo', 'client_email' => 'Correo electrónico', 'client_phone' => 'Teléfono', 'city' => 'Ciudad'] as $field => $label)
                            <div class="col-md-6">
                                <label for="{{ $field }}" class="form-label">{{ $label }} <span aria-hidden="true">*</span></label>
                                <input
                                    id="{{ $field }}"
                                    name="{{ $field }}"
                                    class="form-control @error($field) is-invalid @enderror"
                                    type="{{ match ($field) { 'client_email' => 'email', 'client_phone' => 'tel', default => 'text' } }}"
                                    maxlength="{{ $field === 'client_phone' ? 30 : ($field === 'city' ? 120 : ($field === 'client_name' ? 150 : 255)) }}"
                                    autocomplete="{{ ['client_name' => 'name', 'client_email' => 'email', 'client_phone' => 'tel', 'city' => 'address-level2'][$field] }}"
                                    required
                                    value="{{ old($field) }}"
                                    @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror
                                >
                                @error($field)
                                    <p class="form-feedback" id="{{ $field }}-error" role="alert">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach

                        <div class="col-md-6">
                            <label for="service_id" class="form-label">Servicio <span aria-hidden="true">*</span></label>
                            <select
                                id="service_id"
                                name="service_id"
                                class="form-select @error('service_id') is-invalid @enderror"
                                required
                                @error('service_id') aria-invalid="true" aria-describedby="service_id-error" @enderror
                            >
                                @foreach ($services as $service)
                                    <option
                                        value="{{ $service->id }}"
                                        data-mode="{{ $service->estimation_mode }}"
                                        @selected((string) old('service_id', request('service_id')) === (string) $service->id)
                                    >
                                        {{ $service->name }} — {{ $service->estimation_mode === 'manual' ? 'evaluación manual' : 'área y LOD' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('service_id')
                                <p class="form-feedback" id="service_id-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="project_type" class="form-label">Tipo de proyecto <span aria-hidden="true">*</span></label>
                            <select id="project_type" name="project_type" class="form-select" required>
                                @foreach ($types as $type)
                                    <option @selected(old('project_type', request('project_type')) === $type)>{{ $type }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="area_m2" class="form-label">Área (m², si aplica)</label>
                            <input
                                id="area_m2"
                                name="area_m2"
                                type="number"
                                min="0.01"
                                max="1000000"
                                step="0.01"
                                class="form-control @error('area_m2') is-invalid @enderror"
                                value="{{ old('area_m2', request('area_m2')) }}"
                                @error('area_m2') aria-invalid="true" aria-describedby="area_m2-help area_m2-error" @enderror
                            >
                            <p id="area_m2-help" class="form-text">No se requiere para servicios con evaluación manual.</p>
                            @error('area_m2')
                                <p class="form-feedback" id="area_m2-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="lod_selected" class="form-label">Nivel LOD (si aplica)</label>
                            <select id="lod_selected" name="lod_selected" class="form-select">
                                @foreach (['LOD_200', 'LOD_300', 'LOD_400'] as $level)
                                    <option @selected(old('lod_selected', request('lod_selected', 'LOD_300')) === $level)>{{ $level }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12">
                            <div class="alert alert-light border mb-0">
                                <strong>Estimación referencial en PEN:</strong>
                                <output id="estimate-preview" aria-live="polite" aria-atomic="true">
                                    @if (isset($estimate))
                                        {{ $estimate['estimated_cost_decimal'] === null ? $estimate['message'] : 'Estimación referencial: PEN '.$estimate['estimated_cost_decimal'] }}
                                        Las tarifas disponibles son demostrativas; no constituyen una cotización oficial.
                                    @else
                                        Se calculará con los datos ingresados. Sin JavaScript se confirmará antes de guardar.
                                    @endif
                                </output>
                                <p class="mb-0 mt-2">La evaluación manual, la falta de una tarifa vigente o un área fuera de escala requieren evaluación personalizada y no generan un importe automático. Las tarifas disponibles son demostrativas; el importe oficial se determina en la cotización.</p>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-check-label" for="estimate_confirmed">
                                <input
                                    id="estimate_confirmed"
                                    name="estimate_confirmed"
                                    type="checkbox"
                                    value="1"
                                    required
                                    @checked(old('estimate_confirmed'))
                                    @error('estimate_confirmed') aria-invalid="true" aria-describedby="estimate-confirmed-error" @enderror
                                >
                                Revisé la estimación o el motivo de evaluación personalizada. <span aria-hidden="true">*</span>
                            </label>
                            @error('estimate_confirmed')
                                <p class="form-feedback" id="estimate-confirmed-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label for="description" class="form-label">Descripción del proyecto <span aria-hidden="true">*</span></label>
                            <textarea
                                id="description"
                                name="description"
                                class="form-control @error('description') is-invalid @enderror"
                                required
                                minlength="10"
                                maxlength="10000"
                                rows="5"
                                @error('description') aria-invalid="true" aria-describedby="description-error" @enderror
                            >{{ old('description') }}</textarea>
                            @error('description')
                                <p class="form-feedback" id="description-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="attachment" class="form-label">Adjunto privado (opcional, máximo 50 MiB)</label>
                            <input
                                id="attachment"
                                name="attachment"
                                type="file"
                                class="form-control @error('attachment') is-invalid @enderror"
                                accept=".pdf,.jpg,.jpeg,.png,.dwg,.zip"
                                aria-describedby="attachment-help file-selection file-error"
                                @error('attachment') aria-invalid="true" @enderror
                            >
                            <p id="attachment-help" class="form-text">PDF, JPG, JPEG, PNG, DWG o ZIP. No se ejecutan ni descomprimen archivos. Si supera 50 MiB, comparte un enlace externo.</p>
                            <p id="file-selection" class="file-selection" data-file-selection aria-live="polite"></p>
                            <button type="button" class="btn btn-outline-secondary btn-sm d-none" data-remove-file aria-controls="attachment">Retirar archivo seleccionado</button>
                            <p id="file-error" data-file-error tabindex="-1" class="form-feedback" role="alert" aria-live="assertive"></p>
                            @error('attachment')
                                <p class="form-feedback" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="external_url" class="form-label">Enlace externo http/https (opcional)</label>
                            <input
                                id="external_url"
                                name="external_url"
                                type="url"
                                maxlength="2048"
                                class="form-control @error('external_url') is-invalid @enderror"
                                value="{{ old('external_url') }}"
                                autocomplete="url"
                                @error('external_url') aria-invalid="true" aria-describedby="external_url-error" @enderror
                            >
                            <p class="form-text">Alternativa para archivos de más de 50 MiB.</p>
                            @error('external_url')
                                <p class="form-feedback" id="external_url-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-check-label" for="consent">
                                <input
                                    id="consent"
                                    type="checkbox"
                                    name="consent"
                                    value="1"
                                    required
                                    @checked(old('consent'))
                                    @error('consent') aria-invalid="true" aria-describedby="consent-error" @enderror
                                >
                                Autorizo el tratamiento de mis datos y archivos para evaluar la solicitud y contactarme. <span aria-hidden="true">*</span>
                            </label>
                            @error('consent')
                                <p class="form-feedback" id="consent-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <button class="btn btn-dark mt-4" type="submit" data-loading-label="Registrando solicitud…" @disabled($services->isEmpty())>
                        Confirmar estimación y registrar solicitud
                    </button>
                    <noscript>
                        <p class="form-text mt-2">Sin JavaScript, si el servidor detecta que la estimación cambió, revisa el nuevo importe y vuelve a confirmar antes de registrar. No se crea una solicitud antes de que el servidor la confirme.</p>
                    </noscript>
                    @if ($services->isEmpty())
                        <p class="form-text mt-2" role="status">No hay servicios disponibles para registrar una solicitud en este momento.</p>
                    @endif
                </form>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('js/service-request.js') }}" defer></script>
    <script src="{{ asset('js/estimate.js') }}" defer></script>
@endpush

@extends('layouts.app')
@section('title','Cotizador referencial | ARAT STUDIO')
@section('content')
<section class="workspace-view">
    <header class="workspace-hero">
        <div class="container">
            <h1>Cotizador referencial</h1>
            <p>Consulta una estimación referencial en soles. El cotizador identifica sus tarifas como demostrativas; el importe oficial se define al preparar la cotización.</p>
        </div>
    </header>
    <div class="workspace-content">
        <div class="container">
            <form id="quotation-form" class="workspace-card" data-estimate-endpoint="{{ route('quotation.calculate') }}">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="service_id" class="form-label">Servicio</label>
                        <select id="service_id" name="service_id" class="form-select" required>
                            @foreach ($services as $service)
                                <option value="{{ $service->id }}" data-mode="{{ $service->estimation_mode }}" @selected($loop->first)>
                                    {{ $service->name }} — {{ $service->estimation_mode === 'manual' ? 'evaluación manual' : 'área y LOD' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="project_type" class="form-label">Tipo de proyecto</label>
                        <select name="project_type" id="project_type" class="form-select" required>
                            @foreach (\App\Http\Requests\PublicSite\StoreServiceRequest::PROJECT_TYPES as $type)
                                <option>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="area_m2" class="form-label">Área (m²)</label>
                        <input id="area_m2" name="area_m2" type="number" class="form-control" min="0.01" max="1000000" step="0.01">
                    </div>
                    <div class="col-md-6">
                        <label for="lod_selected" class="form-label">Nivel LOD</label>
                        <select id="lod_selected" name="lod_selected" class="form-select">
                            @foreach (['LOD_200', 'LOD_300', 'LOD_400'] as $level)
                                <option @selected($level === 'LOD_300')>{{ $level }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="my-4">
                    <h2>Estimación referencial en PEN</h2>
                    <output id="estimate-preview" aria-live="polite" aria-atomic="true">Completa los datos.</output>
                    <p class="form-text">Un servicio manual, un área fuera de escala o la falta de una tarifa vigente requieren evaluación personalizada; no se asigna un importe automático. Las tarifas demostrativas no sustituyen una cotización oficial.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-outline-dark" type="submit">Calcular estimación</button>
                    <a id="request-project-link" class="btn btn-dark" href="{{ route('service-requests.create') }}">Solicitar proyecto</a>
                </div>
            </form>
            @if ($services->isEmpty())
                <p class="alert alert-info mt-3" role="status">No hay servicios disponibles para cotizar en este momento.</p>
            @endif
        </div>
    </div>
</section>
@endsection
@push('scripts')<script src="{{ asset('js/estimate.js') }}" defer></script>@endpush

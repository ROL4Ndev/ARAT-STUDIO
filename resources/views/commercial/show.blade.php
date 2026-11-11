@extends('layouts.app')
@section('title', 'Solicitud '.$serviceRequest->code.' | ARAT STUDIO')
@section('content')
<section class="workspace-view"><header class="workspace-hero"><div class="container"><a href="{{ route('management.dashboard') }}">Volver a Gestión</a><h1>{{ $serviceRequest->code }}</h1><p>Estado: {{ $serviceRequest->status === 'evaluacion' ? 'En evaluación' : ucfirst($serviceRequest->status) }}</p></div></header><div class="workspace-content"><div class="container">
@if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="workspace-card mb-4"><h2>Cliente y proyecto</h2><dl class="row">
@foreach(['client_name' => 'Cliente', 'client_email' => 'Correo', 'client_phone' => 'Teléfono', 'city' => 'Ciudad', 'service' => 'Servicio', 'project_type' => 'Tipo', 'area_m2' => 'Área (m²)', 'lod_selected' => 'LOD', 'description' => 'Descripción'] as $field => $label)<dt class="col-sm-3">{{ $label }}</dt><dd class="col-sm-9" style="white-space:pre-wrap">{{ $serviceRequest->$field }}</dd>@endforeach
<dt class="col-sm-3">Estimación referencial</dt><dd class="col-sm-9">{{ $serviceRequest->estimated_cost === null ? 'Evaluación personalizada' : 'S/ '.$serviceRequest->estimated_cost }} (tarifas de demostración)</dd>
<dt class="col-sm-3">Tarifa utilizada</dt><dd class="col-sm-9">S/ {{ $serviceRequest->base_price_m2 }} por m² × {{ $serviceRequest->lod_factor }}; límite {{ $serviceRequest->max_area_m2 }} m²</dd>
<dt class="col-sm-3">Consentimiento</dt><dd class="col-sm-9">{{ $serviceRequest->consented_at->format('d/m/Y H:i') }}</dd>
</dl>
@if($serviceRequest->attachment_path)<p>Adjunto: {{ $serviceRequest->attachment_name }}</p><a class="btn btn-outline-dark" href="{{ route('commercial.requests.attachment', $serviceRequest) }}">Descargar archivo</a>
@if(in_array(strtolower(pathinfo($serviceRequest->attachment_path, PATHINFO_EXTENSION)), ['pdf','png','jpg','jpeg']))<a class="btn btn-outline-dark" target="_blank" rel="noopener noreferrer" href="{{ route('commercial.requests.attachment', [$serviceRequest, 'preview' => 1]) }}">Previsualizar</a>@endif @endif
@if($serviceRequest->external_url)<p class="mt-3"><a href="{{ $serviceRequest->external_url }}" target="_blank" rel="noopener noreferrer">Abrir enlace externo del cliente</a></p>@endif
@if($serviceRequest->rejection_reason)<p>Motivo del rechazo: {{ $serviceRequest->rejection_reason }}</p>@endif</div>
@if(in_array($serviceRequest->status, ['pendiente','evaluacion']))
<form class="workspace-card mb-4" method="POST" action="{{ route('commercial.requests.transition', $serviceRequest) }}">@csrf @method('PATCH')<h2>Evaluación comercial</h2><input type="hidden" name="expected_status" value="{{ $serviceRequest->status }}"><label for="new-status" class="form-label">Siguiente estado</label><select name="status" id="new-status" class="form-select mb-3">@if($serviceRequest->status === 'pendiente')<option value="evaluacion">En evaluación</option>@else<option value="aprobado" @selected(old('status') === 'aprobado')>Aprobar</option><option value="rechazado" @selected(old('status') === 'rechazado')>Rechazar</option>@endif</select><label for="observation" class="form-label">Observación o motivo de rechazo (obligatorio al rechazar)</label><textarea id="observation" name="observation" class="form-control" maxlength="5000">{{ old('observation') }}</textarea><button class="btn btn-dark mt-3">Guardar estado</button></form>
@endif
@includeIf('commercial.quotation-form')
<div class="workspace-card"><h2>Historial</h2><ol>@foreach($serviceRequest->histories as $history)<li class="mb-3">{{ $history->created_at->format('d/m/Y H:i:s') }} — {{ $history->user?->name ?? 'Registro público' }} — {{ $history->action }}: {{ $history->previous_status ?? 'Inicio' }} → {{ $history->new_status }}<br>{{ $history->observation }}</li>@endforeach</ol></div>
</div></div></section>
@endsection

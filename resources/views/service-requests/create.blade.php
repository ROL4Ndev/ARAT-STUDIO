@extends('layouts.app')
@section('title', 'Solicitar proyecto | ARAT STUDIO')
@section('content')
<section class="workspace-view"><header class="workspace-hero"><div class="container"><h1>Solicitar proyecto</h1><p>Cuéntanos qué necesitas. Evaluaremos el alcance y prepararemos una cotización oficial.</p></div></header>
<div class="workspace-content"><div class="container"><form class="workspace-card" id="project-request" method="POST" enctype="multipart/form-data" action="{{ route('service-requests.store') }}">
@csrf
<input type="hidden" name="submission_token" value="{{ old('submission_token', $token) }}">
@if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="row g-3">
@foreach(['client_name' => 'Nombre completo', 'client_email' => 'Correo electrónico', 'client_phone' => 'Teléfono', 'city' => 'Ciudad'] as $field => $label)
<div class="col-md-6"><label class="form-label" for="{{ $field }}">{{ $label }}</label><input class="form-control" id="{{ $field }}" name="{{ $field }}" type="{{ $field === 'client_email' ? 'email' : 'text' }}" value="{{ old($field) }}" required maxlength="{{ $field === 'client_phone' ? 30 : ($field === 'city' ? 120 : ($field === 'client_name' ? 150 : 255)) }}"></div>
@endforeach
@foreach(['service' => ['Servicio', $services], 'project_type' => ['Tipo de proyecto', $types], 'lod_selected' => ['Nivel LOD', ['LOD_200', 'LOD_300', 'LOD_400']]] as $field => [$label, $options])
<div class="col-md-4"><label class="form-label" for="{{ $field }}">{{ $label }}</label><select class="form-select" id="{{ $field }}" name="{{ $field }}" required><option value="">Seleccionar</option>@foreach($options as $option)<option value="{{ $option }}" @selected(old($field, request()->query($field)) === $option)>{{ str_replace('_', ' ', $option) }}</option>@endforeach</select></div>
@endforeach
<div class="col-md-4"><label class="form-label" for="area_m2">Área (m²)</label><input class="form-control" type="number" id="area_m2" name="area_m2" min="0.01" max="1000000" step="0.01" required value="{{ old('area_m2', request()->query('area_m2')) }}"></div>
<div class="col-12"><label class="form-label" for="description">Descripción del proyecto</label><textarea class="form-control" id="description" name="description" required minlength="10" maxlength="10000" rows="5">{{ old('description') }}</textarea></div>
<div class="col-md-6"><label class="form-label" for="attachment">Archivo privado (opcional, hasta 50 MB)</label><input class="form-control" type="file" id="attachment" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.dwg,.zip"><p class="form-text">PDF, JPG, JPEG, PNG, DWG o ZIP. Si excede 50 MB, utiliza un enlace externo. Al corregir datos debes volver a seleccionar el archivo.</p><p id="file-error" class="text-danger" role="alert"></p></div>
<div class="col-md-6"><label class="form-label" for="external_url">Enlace externo (opcional)</label><input class="form-control" type="url" id="external_url" name="external_url" maxlength="2048" value="{{ old('external_url') }}"><p class="form-text">Enlace http o https con permisos para nuestro equipo.</p></div>
<div class="col-12"><p>El servidor recalculará la estimación con tarifas de demostración. Sobre 500 m², o el límite configurado del LOD, corresponde evaluación personalizada.</p><label><input type="checkbox" name="consent" value="1" required @checked(old('consent'))> Autorizo el tratamiento de mis datos y archivos para evaluar esta solicitud y contactarme.</label></div>
</div><button class="btn btn-dark mt-4" type="submit">Registrar solicitud</button>
</form></div></div></section>
@endsection
@push('scripts')<script src="{{ asset('js/service-request.js') }}" defer></script>@endpush

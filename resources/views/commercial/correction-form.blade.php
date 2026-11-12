@if($serviceRequest->status==='observada')
<form method="POST" enctype="multipart/form-data" data-document-upload class="border p-3 mb-4" action="{{ route('commercial.requests.correct',$serviceRequest) }}">@csrf
<h3>Registrar corrección con evidencia</h3><p>Se registra como acción del personal. No representa verificación de correo ni aceptación del cliente; los contactos se conservan.</p>
<input type="hidden" name="lock_version" value="{{ $serviceRequest->lock_version }}">
<label for="staff_project_type">Tipo de proyecto</label><select name="project_type" id="staff_project_type" class="form-select">@foreach(['Vivienda','Comercio','Oficina','Otro'] as $type)<option @selected(old('project_type',$serviceRequest->project_type)===$type)>{{ $type }}</option>@endforeach</select>
@if(($serviceRequest->service_snapshot['estimation_mode']??'area_lod')==='area_lod')<label for="staff_area">Área m²</label><input id="staff_area" name="area_m2" type="number" step="0.01" min="0.01" max="1000000" class="form-control" required value="{{ old('area_m2',$serviceRequest->area_m2) }}"><label for="staff_lod">LOD</label><select id="staff_lod" name="lod_selected" class="form-select">@foreach(['LOD_200','LOD_300','LOD_400'] as $lod)<option @selected(old('lod_selected',$serviceRequest->lod_selected)===$lod)>{{ $lod }}</option>@endforeach</select>@endif
<label for="staff_description">Descripción corregida</label><textarea id="staff_description" name="description" required minlength="10" maxlength="10000" class="form-control">{{ old('description',$serviceRequest->description) }}</textarea>
<label for="staff_file">Archivo de la nueva revisión (hasta 50 MiB)</label><input id="staff_file" name="attachment" type="file" accept=".pdf,.jpg,.jpeg,.png,.dwg,.zip" class="form-control"><p data-file-error class="text-danger" role="alert" tabindex="-1"></p>
<label for="staff_url">Enlace externo opcional</label><input id="staff_url" name="external_url" type="url" class="form-control" value="{{ old('external_url',$serviceRequest->external_url) }}">
<label for="staff_reason">Motivo de la corrección</label><textarea id="staff_reason" name="reason" required minlength="5" maxlength="5000" class="form-control">{{ old('reason') }}</textarea>
<label for="staff_evidence">Evidencia y referencia de la comunicación (nota interna, sin contraseñas ni tokens)</label><textarea id="staff_evidence" name="evidence" required minlength="20" maxlength="5000" class="form-control">{{ old('evidence') }}</textarea>
<button class="btn btn-dark mt-3" type="submit">Confirmar corrección con evidencia</button></form>
@push('scripts')<script src="{{ asset('js/service-request.js') }}" defer></script>@endpush
@endif

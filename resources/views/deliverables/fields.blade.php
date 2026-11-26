<input type="hidden" name="operation_key" value="{{ old('operation_key', (string) Illuminate\Support\Str::uuid()) }}">
<label for="delivery-title" class="form-label">Título del entregable <span aria-hidden="true">*</span></label>
<input id="delivery-title" name="title" class="form-control mb-3" required maxlength="160" value="{{ old('title', $version?->title) }}">
@error('title')<p class="form-feedback" role="alert">{{ $message }}</p>@enderror
<label for="delivery-description" class="form-label">Descripción para el cliente <span aria-hidden="true">*</span></label>
<textarea id="delivery-description" name="description" class="form-control mb-3" required maxlength="5000" rows="4">{{ old('description', $version?->description) }}</textarea>
@error('description')<p class="form-feedback" role="alert">{{ $message }}</p>@enderror
<label for="delivery-file" class="form-label">Archivo PDF, PNG o JPEG</label>
<input id="delivery-file" name="attachment" type="file" class="form-control" accept="application/pdf,image/png,image/jpeg,.pdf,.png,.jpg,.jpeg" aria-describedby="delivery-file-help">
<p id="delivery-file-help" class="form-text">Máximo 10 MiB. Puedes guardar el borrador sin archivo; para enviarlo debes adjuntar uno válido. El archivo se conserva de forma privada.</p>
@error('attachment')<p class="form-feedback" role="alert">{{ $message }}</p>@enderror
<label for="delivery-note" class="form-label mt-3">Nota interna (opcional)</label>
<textarea id="delivery-note" name="internal_note" class="form-control" maxlength="2000" rows="2">{{ old('internal_note', $version?->internal_note) }}</textarea>
<p class="form-text">Esta nota y los borradores no se muestran al cliente.</p>
@error('internal_note')<p class="form-feedback" role="alert">{{ $message }}</p>@enderror

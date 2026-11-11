<div class="admin-form__field">
    <label class="form-label" for="name">Nombre de la categoría</label>
    <input
        class="form-control @error('name') is-invalid @enderror"
        id="name"
        name="name"
        type="text"
        maxlength="100"
        value="{{ old('name', $category->name ?? '') }}"
        required
        autofocus
        @error('name') aria-describedby="name-error" @enderror
    >
    @error('name')
        <p class="invalid-feedback d-block" id="name-error" role="alert">{{ $message }}</p>
    @enderror
</div>

@isset($category)
    <p class="admin-form__hint">El slug se genera automáticamente: <strong>{{ $category->slug }}</strong></p>
@else
    <p class="admin-form__hint">El slug único se generará a partir del nombre.</p>
@endisset

<div class="admin-form__actions">
    <button class="btn btn-dark admin-button" type="submit">{{ $buttonLabel }}</button>
    <a class="btn btn-outline-dark admin-button" href="{{ route('admin.categories.index') }}">Cancelar</a>
</div>

<div class="admin-form__field">
    <label class="form-label" for="title">Título</label>
    <input
        class="form-control @error('title') is-invalid @enderror"
        id="title"
        name="title"
        type="text"
        maxlength="180"
        value="{{ old('title', $project->title ?? '') }}"
        required
        autofocus
        @error('title') aria-describedby="title-error" @enderror
    >
    @error('title')
        <p class="invalid-feedback d-block" id="title-error" role="alert">{{ $message }}</p>
    @enderror
</div>

<div class="admin-form__field">
    <label class="form-label" for="category_id">Categoría</label>
    <select
        class="form-select @error('category_id') is-invalid @enderror"
        id="category_id"
        name="category_id"
        required
        @error('category_id') aria-describedby="category-error" @enderror
    >
        <option value="">Selecciona una categoría</option>
        @foreach ($categories as $category)
            <option
                value="{{ $category->id }}"
                @selected((string) old('category_id', $project->category_id ?? '') === (string) $category->id)
            >
                {{ $category->name }}
            </option>
        @endforeach
    </select>
    @error('category_id')
        <p class="invalid-feedback d-block" id="category-error" role="alert">{{ $message }}</p>
    @enderror
</div>

<div class="admin-form__field">
    <label class="form-label" for="description">Descripción</label>
    <textarea
        class="form-control @error('description') is-invalid @enderror"
        id="description"
        name="description"
        rows="6"
        maxlength="10000"
        required
        @error('description') aria-describedby="description-error" @enderror
    >{{ old('description', $project->description ?? '') }}</textarea>
    @error('description')
        <p class="invalid-feedback d-block" id="description-error" role="alert">{{ $message }}</p>
    @enderror
</div>

<div class="admin-form__field">
    <label class="form-label" for="cover_image">Portada (JPG, PNG o WebP; máximo 5 MB)</label>
    <input
        class="form-control @error('cover_image') is-invalid @enderror"
        id="cover_image"
        name="cover_image"
        type="file"
        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
        @if (! isset($project)) required @endif
        @error('cover_image') aria-describedby="cover-error" @enderror
    >
    @error('cover_image')
        <p class="invalid-feedback d-block" id="cover-error" role="alert">{{ $message }}</p>
    @enderror
    @isset($project)
        <img class="admin-project-cover" src="{{ $project->cover_image_url }}" alt="Portada actual de {{ $project->title }}">
        <p class="admin-form__hint">Deja el campo vacío para conservar la portada actual.</p>
    @endisset
</div>

<div class="form-check admin-form__checkbox">
    <input
        class="form-check-input"
        id="is_active"
        name="is_active"
        type="checkbox"
        value="1"
        @checked((bool) old('is_active', $project->is_active ?? true))
    >
    <label class="form-check-label" for="is_active">Proyecto activo y visible en el portafolio</label>
</div>

@isset($project)
    <p class="admin-form__hint">Slug: <strong>{{ $project->slug }}</strong> (se conserva al editar).</p>
@else
    <p class="admin-form__hint">El slug único se generará automáticamente a partir del título.</p>
@endisset

<div class="admin-form__actions">
    <button class="btn btn-dark admin-button" type="submit">{{ $buttonLabel }}</button>
    <a class="btn btn-outline-dark admin-button" href="{{ route('admin.projects.index') }}">Cancelar</a>
</div>

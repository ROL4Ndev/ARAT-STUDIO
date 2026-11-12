@extends('admin.layout')

@section('title', ($item->exists ? 'Editar '.$label : 'Crear '.$label).' | ARAT STUDIO')
@section('admin-title', ($item->exists ? 'Editar: ' : 'Crear: ').$label)

@section('admin-content')
    @include('shared.errors')

    <form
        class="admin-form"
        method="POST"
        enctype="multipart/form-data"
        action="{{ $item->exists ? route('admin.'.$kind.'.update', $item->id) : route('admin.'.$kind.'.store') }}"
    >
        @csrf
        @if ($item->exists)
            @method('PUT')
            <input type="hidden" name="lock_version" value="{{ $item->lock_version }}">
        @endif

        @if ($kind === 'tariffs')
            <p>Solo se editan precios de un borrador. Editar una tarifa publicada crea otra versión borrador y no cambia la vigente.</p>
            @if (! $item->exists)
                <label for="lod_level" class="form-label">Familia LOD <span aria-hidden="true">*</span></label>
                <select id="lod_level" name="lod_level" class="form-select" required>
                    @foreach (['LOD_200', 'LOD_300', 'LOD_400'] as $level)
                        <option @selected(old('lod_level') === $level)>{{ $level }}</option>
                    @endforeach
                </select>
                @error('lod_level')
                    <p class="form-feedback" role="alert">{{ $message }}</p>
                @enderror
            @else
                <p>Familia: {{ $item->lod_level }} · Versión {{ $item->version }}</p>
            @endif

            @foreach (['base_price_m2' => 'Tarifa base PEN por m²', 'lod_factor' => 'Factor LOD', 'max_area_m2' => 'Área máxima m²'] as $field => $title)
                <label for="{{ $field }}" class="form-label">{{ $title }} <span aria-hidden="true">*</span></label>
                <input
                    id="{{ $field }}"
                    class="form-control @error($field) is-invalid @enderror"
                    name="{{ $field }}"
                    type="number"
                    step="{{ $field === 'lod_factor' ? '0.001' : '0.01' }}"
                    min="{{ $field === 'lod_factor' ? '0.001' : '0.01' }}"
                    required
                    value="{{ old($field, $item->$field) }}"
                    @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror
                >
                @error($field)
                    <p class="form-feedback" id="{{ $field }}-error" role="alert">{{ $message }}</p>
                @enderror
            @endforeach
        @else
            @if ($kind === 'services' && ! $item->exists)
                <label for="code" class="form-label">Código único e inmutable <span aria-hidden="true">*</span></label>
                <input class="form-control @error('code') is-invalid @enderror" id="code" name="code" required maxlength="60" autocomplete="off" value="{{ old('code') }}" @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>
                <p class="form-text">Usa mayúsculas, números y guiones.</p>
                @error('code')
                    <p class="form-feedback" id="code-error" role="alert">{{ $message }}</p>
                @enderror
            @elseif ($kind === 'services')
                <p>Código único e inmutable: <strong>{{ $item->code }}</strong></p>
            @endif

            @php($field = $kind === 'projects' ? 'title' : 'name')
            <label for="{{ $field }}" class="form-label">{{ $kind === 'projects' ? 'Título' : 'Nombre' }} <span aria-hidden="true">*</span></label>
            <input
                id="{{ $field }}"
                name="{{ $field }}"
                class="form-control @error($field) is-invalid @enderror"
                required
                maxlength="{{ $kind === 'categories' ? 100 : ($kind === 'projects' ? 180 : 150) }}"
                value="{{ old($field, $item->$field) }}"
                @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror
            >
            @error($field)
                <p class="form-feedback" id="{{ $field }}-error" role="alert">{{ $message }}</p>
            @enderror

            <label for="description" class="form-label">Descripción{{ $kind === 'categories' ? ' (opcional)' : '' }}</label>
            <textarea
                id="description"
                class="form-control @error('description') is-invalid @enderror"
                name="description"
                rows="5"
                maxlength="{{ $kind === 'projects' ? 10000 : 5000 }}"
                @required($kind !== 'categories')
                @error('description') aria-invalid="true" aria-describedby="description-error" @enderror
            >{{ old('description', $item->description) }}</textarea>
            @error('description')
                <p class="form-feedback" id="description-error" role="alert">{{ $message }}</p>
            @enderror

            @if ($kind === 'projects')
                <label for="category_id" class="form-label">Categoría <span aria-hidden="true">*</span></label>
                <select id="category_id" name="category_id" class="form-select @error('category_id') is-invalid @enderror" required @error('category_id') aria-invalid="true" aria-describedby="category_id-error" @enderror>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) old('category_id', $item->category_id) === (string) $category->id)>
                            {{ $category->name }}{{ $category->is_active ? '' : ' — inactiva' }}
                        </option>
                    @endforeach
                </select>
                <p class="form-text">La categoría debe estar activa para publicar el proyecto.</p>
                @error('category_id')
                    <p class="form-feedback" id="category_id-error" role="alert">{{ $message }}</p>
                @enderror

                <label for="cover_image" class="form-label">Portada JPG, PNG o WebP · máximo 5 MiB{{ $item->exists ? '' : ' · obligatoria' }}</label>
                <input
                    id="cover_image"
                    name="cover_image"
                    type="file"
                    accept=".jpg,.jpeg,.png,.webp"
                    class="form-control @error('cover_image') is-invalid @enderror"
                    @required(! $item->exists)
                    @error('cover_image') aria-invalid="true" aria-describedby="cover_image-error" @enderror
                >
                @error('cover_image')
                    <p class="form-feedback" id="cover_image-error" role="alert">{{ $message }}</p>
                @enderror
                @if ($item->exists)
                    <img class="admin-project-cover" src="{{ $item->cover_image_url }}" alt="Portada actual de {{ $item->title }}" width="800" height="600">
                    <p class="form-text">Deja el campo vacío para conservar la portada actual. Las portadas anteriores se conservan.</p>
                @endif

                <input type="hidden" name="is_active" value="0">
                <div class="form-check admin-form__checkbox">
                    <input id="is_active" type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->is_active))>
                    <label class="form-check-label" for="is_active">Publicar proyecto activo (sin marcar queda como borrador)</label>
                </div>
            @endif

            @if ($kind === 'services')
                <label for="audience" class="form-label">Público objetivo <span aria-hidden="true">*</span></label>
                <select class="form-select @error('audience') is-invalid @enderror" id="audience" name="audience" required @error('audience') aria-invalid="true" aria-describedby="audience-error" @enderror>
                    @foreach (['general' => 'General', 'estudiantes' => 'Estudiantes'] as $value => $text)
                        <option value="{{ $value }}" @selected(old('audience', $item->audience) === $value)>{{ $text }}</option>
                    @endforeach
                </select>
                @error('audience')
                    <p class="form-feedback" id="audience-error" role="alert">{{ $message }}</p>
                @enderror
                <label for="display_order" class="form-label">Orden de presentación <span aria-hidden="true">*</span></label>
                <input class="form-control @error('display_order') is-invalid @enderror" type="number" min="0" name="display_order" id="display_order" required value="{{ old('display_order', $item->display_order ?? 0) }}" @error('display_order') aria-invalid="true" aria-describedby="display-order-error" @enderror>
                @error('display_order')
                    <p class="form-feedback" id="display-order-error" role="alert">{{ $message }}</p>
                @enderror
                <label for="estimation_mode" class="form-label">Tipo de estimación <span aria-hidden="true">*</span></label>
                <select class="form-select @error('estimation_mode') is-invalid @enderror" id="estimation_mode" name="estimation_mode" required @error('estimation_mode') aria-invalid="true" aria-describedby="estimation-mode-error" @enderror>
                    @foreach (['manual' => 'Evaluación manual', 'area_lod' => 'Área y LOD'] as $value => $text)
                        <option value="{{ $value }}" @selected(old('estimation_mode', $item->estimation_mode) === $value)>{{ $text }}</option>
                    @endforeach
                </select>
                @error('estimation_mode')
                    <p class="form-feedback" id="estimation-mode-error" role="alert">{{ $message }}</p>
                @enderror
            @endif
        @endif

        <label for="reason" class="form-label">Motivo del alta o cambio <span aria-hidden="true">*</span></label>
        <textarea id="reason" name="reason" class="form-control @error('reason') is-invalid @enderror" required minlength="5" maxlength="2000" @error('reason') aria-invalid="true" aria-describedby="reason-error" @enderror>{{ old('reason') }}</textarea>
        @error('reason')
            <p class="form-feedback" id="reason-error" role="alert">{{ $message }}</p>
        @enderror

        <div class="admin-form__actions">
            <button class="btn btn-dark admin-button" type="submit" data-loading-label="Guardando…">{{ $item->exists ? 'Guardar cambios' : 'Crear '.mb_strtolower(rtrim($label, 's')) }}</button>
            <a class="btn btn-outline-dark admin-button" href="{{ route('admin.'.$kind.'.index') }}">Volver al listado</a>
        </div>
    </form>
@endsection

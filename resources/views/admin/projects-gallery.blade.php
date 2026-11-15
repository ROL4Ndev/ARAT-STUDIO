<section class="workspace-card mt-4" id="galeria" aria-labelledby="gallery-admin-title">
    <h2 id="gallery-admin-title">Imágenes del proyecto</h2>
    <p>Vincula las fotografías de una carpeta o sube imágenes nuevas. Archivar una imagen la retira de la galería
        pública y conserva el original.</p>
    <details class="secondary-details">
        <summary>Añadir imágenes</summary>
        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.gallery.add', $project->id) }}">
            @csrf
            <input type="hidden" name="action" value="gallery-add">
            <input type="hidden" name="lock_version" value="{{ $project->lock_version }}">
            <label class="form-label" for="source_folder">Carpeta existente (opcional)</label>
            <select class="form-select" name="source_folder" id="source_folder">
                <option value="">Sin carpeta</option>
                @foreach ($imageFolders as $folder)
                    <option value="{{ $folder }}" @selected(old('source_folder') === $folder)>{{ $folder }}</option>
                @endforeach
            </select>
            <p class="form-text">Se vinculan las imágenes sin copiarlas ni modificar el contenido del proyecto.</p>
            <label class="form-label" for="gallery_uploads">Imágenes nuevas (opcional)</label>
            <input class="form-control" name="images[]" id="gallery_uploads" type="file" multiple
                accept=".jpg,.jpeg,.png,.webp">
            <p class="form-text">Hasta 20 imágenes JPG, PNG o WebP de 5 MiB cada una. Las repetidas no crean nuevas
                entradas.</p>
            <label class="form-label" for="gallery_add_reason">Motivo *</label>
            <textarea class="form-control" name="reason" id="gallery_add_reason" required minlength="5" maxlength="2000">{{ old('reason') }}</textarea>
            <button class="btn btn-dark mt-3" type="submit">Añadir a la galería</button>
        </form>
    </details>
    @if ($project->images->where('is_active', true)->isNotEmpty())
        <form id="gallery-order" method="POST" action="{{ route('admin.gallery.order', $project->id) }}">
            @csrf
            <input type="hidden" name="action" value="gallery-order">
            <input type="hidden" name="lock_version" value="{{ $project->lock_version }}">
            <label class="form-label" for="gallery_order_reason">Motivo del orden *</label>
            <input class="form-control" id="gallery_order_reason" name="reason" required minlength="5"
                maxlength="2000" value="{{ old('reason') }}">
            <p class="form-text">Asigna un número distinto a cada imagen activa. Los números menores se muestran
                primero.</p>
            <button class="btn btn-outline-dark mb-4" type="submit">Guardar orden de imágenes</button>
        </form>
    @endif
    <div class="admin-gallery-grid">
        @forelse($project->images as $image)
            <article class="admin-gallery-item">
                <a href="{{ $image->url }}" target="_blank" rel="noopener">
                    <img src="{{ $image->thumbnail_url }}"
                        alt="Imagen {{ $loop->iteration }} de {{ $project->title }}" loading="lazy"
                        width="{{ $image->width }}" height="{{ $image->height }}">
                </a>
                <p>
                    @include('shared.status-label', ['value' => $image->is_active ? 'activo' : 'inactivo']) @if ($project->cover_image === $image->path)
                        <strong>Portada actual</strong>
                    @endif
                </p>
                @if ($image->is_active)
                    <label class="form-label" for="position-{{ $image->id }}">Orden de imagen
                        {{ $loop->iteration }}</label>
                    <input class="form-control mb-3" form="gallery-order" id="position-{{ $image->id }}"
                        name="positions[{{ $image->id }}]" type="number" min="0" max="100000" required
                        value="{{ $image->sort_order }}">
                @endif
                @if ($project->cover_image !== $image->path)
                    <details class="secondary-details">
                        <summary>Opciones de imagen {{ $loop->iteration }}</summary>
                        @if ($image->is_active && $project->cover_image !== $image->path)
                            <form method="POST" action="{{ route('admin.gallery.cover', [$project->id, $image]) }}"
                                data-confirm="¿Usar esta imagen como portada? La anterior se conservará en el historial.">
                                @csrf
                                <input type="hidden" name="action" value="gallery-cover-{{ $image->id }}">
                                <input type="hidden" name="lock_version" value="{{ $project->lock_version }}">
                                <label class="form-label" for="cover-reason-{{ $image->id }}">Motivo de la portada
                                    *</label>
                                <input class="form-control" id="cover-reason-{{ $image->id }}" name="reason"
                                    required minlength="5" maxlength="2000">
                                <button class="btn btn-outline-dark mt-2" type="submit">Usar como portada</button>
                            </form>
                        @endif
                        @if ($project->cover_image !== $image->path)
                            <form method="POST" action="{{ route('admin.gallery.status', [$project->id, $image]) }}">
                                @csrf
                                <input type="hidden" name="action" value="gallery-status-{{ $image->id }}">
                                <input type="hidden" name="lock_version" value="{{ $project->lock_version }}">
                                <input type="hidden" name="is_active" value="{{ $image->is_active ? 0 : 1 }}">
                                <label class="form-label" for="image-reason-{{ $image->id }}">Motivo del estado
                                    *</label>
                                <input class="form-control" id="image-reason-{{ $image->id }}" name="reason"
                                    required minlength="5" maxlength="2000">
                                <button class="btn btn-outline-dark mt-2"
                                    type="submit">{{ $image->is_active ? 'Archivar imagen' : 'Reactivar imagen' }}</button>
                            </form>
                        @endif
                    </details>
                @endif
            </article>
        @empty
            <p class="empty-state">Todavía no hay imágenes en la galería. La portada actual se conserva.</p>
        @endforelse
    </div>
</section>

<section class="workspace-card mb-4" id="entregables" aria-labelledby="client-deliverables-title">
    <h2 id="client-deliverables-title">Entregables</h2>
    <p>Descarga los documentos enviados por el estudio. Cada versión conserva su archivo y tu respuesta.</p>
    @forelse ($deliverables as $deliverable)
        <article class="border-top pt-4 mt-4" aria-labelledby="delivery-{{ $deliverable->code }}">
            <h3 id="delivery-{{ $deliverable->code }}">{{ $deliverable->versions->first()->title }}</h3>
            <p>@include('shared.status-label', ['value' => $deliverable->status]) · Cotización {{ $deliverable->quotation->code }}</p>
            @foreach ($deliverable->versions as $version)
                <div class="border-top pt-3 mt-3">
                    <h4>Versión {{ $version->number }}{{ $loop->first ? ' · última enviada' : ' · conservada' }}</h4>
                    <p class="long-form-text preserve-lines">{{ $version->description }}</p>
                    <p class="form-text">Enviada el {{ $version->sent_at->timezone('America/Lima')->format('d/m/Y H:i') }} (Lima).</p>
                    <a class="btn btn-outline-dark mb-3" href="{{ route('client.deliverables.download', [$deliverable, $version->number]) }}">Descargar {{ $version->original_name }} · versión {{ $version->number }}</a>
                    @if ($version->decision)
                        <p>@include('shared.status-label', ['value' => $version->decision]) · {{ $version->responded_at->timezone('America/Lima')->format('d/m/Y H:i') }} (Lima)</p>
                        @if ($version->comment)<p class="preserve-lines long-form-text">{{ $version->comment }}</p>@endif
                    @elseif ($loop->first && $deliverable->status === 'en_revision')
                        <form method="POST" action="{{ route('client.deliverables.respond', [$deliverable, $version->number]) }}" data-confirm="¿Dar conformidad a esta versión del entregable? Quedará finalizada y tu respuesta se conservará.">
                            @csrf
                            <input type="hidden" name="action" value="delivery-{{ $deliverable->code }}">
                            <input type="hidden" name="decision" value="conforme">
                            <input type="hidden" name="lock_version" value="{{ $deliverable->lock_version }}">
                            <div class="form-check mb-3">
                                <input class="form-check-input" id="confirm-{{ $deliverable->code }}" type="checkbox" name="confirmation" value="1" required>
                                <label class="form-check-label" for="confirm-{{ $deliverable->code }}">Revisé la versión {{ $version->number }} y confirmo mi conformidad.</label>
                            </div>
                            @if(old('action') === 'delivery-'.$deliverable->code)@error('confirmation')<p class="form-feedback" role="alert">{{ $message }}</p>@enderror @endif
                            <button class="btn btn-dark" type="submit" data-loading-label="Registrando conformidad…">Dar conformidad</button>
                        </form>
                        <details class="secondary-details mt-3" @if(old('action') === 'delivery-'.$deliverable->code && $errors->has('comment')) open @endif>
                            <summary>Observar esta versión</summary>
                            <form method="POST" action="{{ route('client.deliverables.respond', [$deliverable, $version->number]) }}">
                                @csrf
                                <input type="hidden" name="action" value="delivery-{{ $deliverable->code }}">
                                <input type="hidden" name="decision" value="observado">
                                <input type="hidden" name="lock_version" value="{{ $deliverable->lock_version }}">
                                <label for="observe-{{ $deliverable->code }}" class="form-label">Comentario de revisión <span aria-hidden="true">*</span></label>
                                <textarea id="observe-{{ $deliverable->code }}" name="comment" class="form-control" required maxlength="5000" rows="3">{{ old('action') === 'delivery-'.$deliverable->code ? old('comment') : '' }}</textarea>
                                @if(old('action') === 'delivery-'.$deliverable->code)@error('comment')<p class="form-feedback" role="alert">{{ $message }}</p>@enderror @endif
                                <button class="btn btn-outline-dark mt-3" type="submit" data-loading-label="Registrando observación…">Observar</button>
                            </form>
                        </details>
                    @endif
                </div>
            @endforeach
        </article>
    @empty
        <p class="empty-state">Cuando el estudio envíe un documento para revisión podrás descargarlo y responder desde aquí.</p>
    @endforelse
</section>

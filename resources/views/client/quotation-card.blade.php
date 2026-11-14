@php($isCurrent = $dossier->current_quotation_id === $quotation->id)
<article class="quotation-card {{ $isCurrent ? 'quotation-card--current' : '' }}">
    <p class="section-label">{{ $isCurrent ? 'Propuesta actual del expediente' : 'Documento conservado · versión anterior' }}</p>
    <h3>{{ $quotation->code }} · versión {{ $quotation->version }}</h3>
    @include('shared.status-label', ['value' => $quotation->isExpired() && $quotation->status === 'emitida' ? 'vencida' : $quotation->status])
    <span class="form-text">{{ $isCurrent ? 'Versión actual' : 'Versión anterior' }}</span>
    <strong class="quotation-amount">PEN {{ number_format((float) $quotation->official_amount, 2, '.', ',') }}</strong>
    <p class="form-text">Importe oficial · Emisión: {{ $quotation->issued_at->timezone('America/Lima')->format('d/m/Y H:i') }}<br>Vencimiento: {{ $quotation->expires_at->timezone('America/Lima')->format('d/m/Y H:i') }} (Lima)</p>
    <a class="btn btn-outline-dark mb-4" href="{{ route('client.quotations.download', $quotation) }}">Descargar PDF de la versión {{ $quotation->version }}</a>
    <dl>
        @foreach (['scope' => 'Alcance', 'deliverables' => 'Entregables', 'exclusions' => 'Exclusiones', 'execution_term' => 'Plazo de ejecución'] as $field => $label)
            <dt>{{ $label }}</dt><dd class="preserve-lines long-form-text">{{ $quotation->$field ?? 'No registrado en esta versión' }}</dd>
        @endforeach
    </dl>
    @if ($isCurrent && $quotation->status === 'emitida' && ! $quotation->isExpired() && $dossier->status === 'aprobado')
        <p class="form-text">Tu respuesta quedará registrada en el expediente; no equivale a una firma digital certificada.</p>
        <form method="POST" action="{{ route('client.respond', $quotation) }}" class="mb-3" data-confirm="¿Confirmas aceptar la propuesta vigente por PEN {{ $quotation->official_amount }}? La respuesta se registrará en el expediente.">
            @csrf
            <input type="hidden" name="decision" value="aceptada">
            <input type="hidden" name="lock_version" value="{{ $quotation->lock_version }}">
            <button class="btn btn-dark" type="submit" data-loading-label="Registrando respuesta…">Aceptar propuesta vigente</button>
        </form>
        <details class="secondary-details" @if($errors->has('reason')) open @endif>
            <summary>Prefiero rechazar la propuesta</summary>
            <form method="POST" action="{{ route('client.respond', $quotation) }}" data-confirm="¿Confirmas rechazar esta propuesta? La decisión se registrará en el expediente.">
                @csrf
                <input type="hidden" name="decision" value="rechazada">
                <input type="hidden" name="lock_version" value="{{ $quotation->lock_version }}">
                <label for="reject-{{ $quotation->version }}" class="form-label">Motivo del rechazo <span aria-hidden="true">*</span></label>
                <textarea id="reject-{{ $quotation->version }}" name="reason" class="form-control" required minlength="5" maxlength="5000">{{ old('reason') }}</textarea>
                @error('reason')<p class="form-feedback" role="alert">{{ $message }}</p>@enderror
                <button class="btn btn-outline-dark mt-3" type="submit" data-loading-label="Registrando respuesta…">Rechazar propuesta</button>
            </form>
        </details>
    @elseif ($quotation->status === 'vencida' || ($quotation->isExpired() && $quotation->status === 'emitida'))
        <p class="alert alert-warning mb-0">Esta propuesta ha vencido. Contacta al estudio para solicitar su actualización.</p>
    @endif
</article>

<div class="workspace-card mb-4">
    <h2>Cotizaciones versionadas</h2>
    <p>La estimación del expediente es referencial; cada importe oficial pertenece a una versión concreta de cotización.</p>

    <ul>
        @forelse ($serviceRequest->quotations as $quotation)
            @php($isCurrent = $serviceRequest->currentQuotation?->id === $quotation->id && $quotation->status === 'emitida')
            <li class="mb-3">
                <a href="{{ route('commercial.quotations.show', $quotation) }}">Abrir versión {{ $quotation->version }} · {{ $quotation->code }}</a>
                · @include('shared.status-label', ['value' => $quotation->status])
                · PDF: @include('shared.status-label', ['value' => $quotation->pdf_status])
                · Notificación: @include('shared.status-label', ['value' => $quotation->notification_status])
                · {{ $isCurrent ? 'Versión vigente' : ($quotation->status === 'borrador' || $quotation->status === 'preparando' ? 'Borrador o preparación' : 'Versión anterior') }}
                @if ($quotation->isExpired())
                    · Vencida por fecha
                @endif
            </li>
        @empty
            <li>Aún no hay versiones de cotización.</li>
        @endforelse
    </ul>

    @if ($serviceRequest->status === 'aprobado' && ! $serviceRequest->quotations->contains(fn ($quotation) => in_array($quotation->status, ['borrador', 'preparando'], true)))
        <form method="POST" action="{{ route('commercial.quotations.store', $serviceRequest) }}" data-confirm="Se guardará un borrador de cotización. La versión vigente no cambiará hasta que se emita la nueva. ¿Deseas continuar?">
            @csrf
            <input type="hidden" name="request_lock_version" value="{{ $serviceRequest->lock_version }}">
            <input type="hidden" name="lock_version" value="0">
            @if ($serviceRequest->currentQuotation && in_array($serviceRequest->currentQuotation->status, ['emitida', 'rechazada', 'vencida'], true))
                <input type="hidden" name="source_code" value="{{ $serviceRequest->currentQuotation->code }}">
                <h3>Crear nueva versión</h3>
                <p>La versión anterior se conserva como vigente hasta que el nuevo PDF se emita correctamente.</p>
            @else
                <h3>Crear primera versión</h3>
            @endif
            @include('commercial.quotation-fields', ['draft' => $serviceRequest->currentQuotation])
            <button class="btn btn-dark" type="submit" data-loading-label="Guardando borrador…">Guardar borrador de cotización</button>
        </form>
    @endif
</div>

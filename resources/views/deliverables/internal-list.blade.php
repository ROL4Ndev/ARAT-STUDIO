<section id="entregables" class="workspace-card mb-4" aria-labelledby="deliverables-title">
    <h2 id="deliverables-title">Entregables</h2>
    <p>Documentos del servicio vinculados a su cotización aceptada. Su revisión no modifica el cierre comercial.</p>
    @if ($serviceRequest->quotations->contains('status', 'aceptada'))
        <a class="btn btn-dark mb-3" href="{{ route('commercial.deliverables.create', $serviceRequest) }}">Preparar entregable</a>
    @endif
    @forelse ($serviceRequest->deliverables as $deliverable)
        <article class="border-top py-3">
            <h3>{{ $deliverable->latestVersion->title }}</h3>
            <p>@include('shared.status-label', ['value' => $deliverable->status]) · Última versión {{ $deliverable->latestVersion->number }}{{ $deliverable->latestVersion->sent_at ? ' enviada' : ' en borrador' }}</p>
            <a href="{{ route('commercial.deliverables.show', $deliverable) }}">{{ match ($deliverable->status) { 'borrador' => 'Preparar archivo y envío', 'observado' => 'Consultar observaciones y preparar revisión', 'conforme' => 'Consultar conformidad e historial', default => 'Consultar revisión e historial' } }}</a>
        </article>
    @empty
        <p class="empty-state">{{ $serviceRequest->quotations->contains('status', 'aceptada') ? 'Todavía no hay entregables. Prepara el primer documento para revisión del cliente.' : 'Los entregables estarán disponibles después de la aceptación de una cotización.' }}</p>
    @endforelse
</section>

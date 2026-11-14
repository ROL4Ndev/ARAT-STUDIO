@php
    $currentProposal = $record->quotations->firstWhere('id', $record->current_quotation_id);
    $closed = in_array($record->status, ['rechazado', 'cancelado', 'cerrada'], true);
    $stage = match ($record->status) {
        'pendiente' => 0,
        'evaluacion', 'observada', 'rechazado' => 1,
        'aprobado' => 2,
        'cerrada' => 3,
        default => 0,
    };
    [$nextTitle, $nextText] = match (true) {
        $closed => ['Proceso finalizado', 'Puedes consultar las decisiones, los documentos y el historial de esta solicitud.'],
        $record->status === 'observada' => ['Tu solicitud necesita una corrección', 'Revisa las observaciones del equipo y envía la información que falta. Conservaremos las revisiones anteriores.'],
        $currentProposal?->status === 'vencida' || ($currentProposal?->status === 'emitida' && $currentProposal->isExpired()) => ['La propuesta ha vencido', 'Consulta el documento conservado y contacta al estudio para solicitar una propuesta actualizada.'],
        $currentProposal?->status === 'emitida' => ['Tu propuesta está lista', 'Revisa el alcance, los entregables y el importe oficial. Descarga el PDF antes de registrar tu respuesta.'],
        $record->status === 'aprobado' => ['Evaluación técnica aprobada', 'El estudio preparará la cotización oficial con el alcance y las condiciones de tu proyecto. La estimación sigue siendo referencial.'],
        $record->status === 'evaluacion' => ['Estamos evaluando tu proyecto', 'El equipo revisará la información y te comunicará si necesita algún detalle adicional.'],
        default => ['Solicitud recibida', 'El siguiente paso es la revisión del equipo. Conserva el código para consultar tu expediente.'],
    };
@endphp
<ol class="workflow-steps" aria-label="Avance de la solicitud">
    @foreach (['Solicitud recibida', 'Evaluación técnica', 'Propuesta oficial', 'Respuesta y cierre'] as $label)
        <li class="{{ $loop->index < $stage ? 'is-complete' : '' }}" @if($loop->index === $stage) aria-current="step" @endif>{{ $label }}</li>
    @endforeach
</ol>
@if ($clientView ?? false)
    <section class="next-action mb-4" aria-labelledby="next-action-title">
        <h2 id="next-action-title">{{ $nextTitle }}</h2>
        <p>{{ $nextText }}</p>
        @if ($record->status === 'observada')
            @if ($observation = $record->histories->last(fn ($event) => $event->action === 'observe' && $event->is_public))
                <p class="preserve-lines mt-3"><strong>El equipo te solicita:</strong> {{ $observation->observation }}</p>
            @endif
            <a class="btn btn-dark mt-3" href="#corregir-solicitud">Completar información</a>
        @elseif ($currentProposal?->status === 'emitida' && ! $currentProposal->isExpired() && ! $closed)
            <a class="btn btn-dark mt-3" href="#cotizaciones">Revisar propuesta vigente</a>
        @endif
    </section>
@endif

@php
    $statusValue = mb_strtolower((string) $value);
    $statusLabel = match ($statusValue) {
        'activo', 'active' => 'Activo',
        'inactivo', 'inactive' => 'Inactivo',
        'borrador', 'draft' => 'Borrador',
        'en_revision' => 'En revisión',
        'observado' => 'Observado',
        'conforme' => 'Conforme',
        'activa', 'active_tariff' => 'Activa',
        'inactiva', 'inactive_tariff' => 'Inactiva',
        'pendiente', 'pending' => 'Pendiente',
        'evaluacion' => 'En evaluación',
        'observada' => 'Observada',
        'aprobado' => 'Aprobada',
        'rechazado', 'rechazada' => 'Rechazada',
        'cancelado', 'cancelled' => 'Cancelada',
        'cerrada', 'closed' => 'Cerrada',
        'preparando' => 'PDF en preparación',
        'processing' => 'En procesamiento',
        'emitida', 'issued' => 'Emitida',
        'aceptada', 'accepted' => 'Aceptada',
        'sustituida', 'superseded' => 'Sustituida',
        'vencida', 'expired' => 'Vencida',
        'failed', 'fallida' => 'Fallido',
        'ready', 'lista' => 'PDF listo',
        'simulated' => 'Simulada (entorno local)',
        'sent' => 'Enviada al transporte',
        'unknown' => 'Resultado no confirmado',
        'discarded' => 'Descartado',
        'done' => 'Completado',
        'anulada' => 'Anulada',
        'vigente' => 'Vigente',
        default => ucfirst(str_replace('_', ' ', $statusValue)),
    };
    $statusTone = match ($statusValue) {
        'activo', 'active', 'activa', 'active_tariff', 'aprobado', 'emitida',
        'issued', 'aceptada', 'accepted', 'ready', 'lista', 'sent', 'done', 'conforme' => 'success',
        'failed', 'fallida', 'rechazado', 'rechazada', 'cancelado', 'cancelled', 'vencida', 'expired' => 'error',
        'pendiente', 'pending', 'evaluacion', 'observada', 'preparando', 'processing', 'unknown', 'observado', 'en_revision' => 'attention',
        default => 'muted',
    };
@endphp
<span class="status-label status-label--{{ $statusTone }}">{{ $statusLabel }}</span>

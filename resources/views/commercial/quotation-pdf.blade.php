<!DOCTYPE html>
<html lang="es-PE">
<head>
    <meta charset="UTF-8"><title>{{ $quotation->code }}</title>
    <style>
        @page { margin: 38px 44px 72px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.65; color: #2e2f36; }
        h1 { font-size: 25px; font-weight: normal; margin: 22px 0 8px; }
        h2 { font-size: 12px; color: #324853; margin: 22px 0 8px; page-break-after: avoid; }
        p { margin: 0 0 10px; }
        .brand { width: 100%; border-bottom: 2px solid #324853; padding-bottom: 16px; }
        .brand td { vertical-align: middle; }
        .brand strong { font-size: 15px; letter-spacing: 2px; }
        .brand img { width: 70px; height: 70px; }
        .muted { color: #5d666c; font-size: 9px; }
        .reference { font-size: 9px; word-wrap: break-word; }
        .parties { width: 100%; background: #f2f5f7; margin-top: 20px; padding: 14px; }
        .parties td { width: 50%; vertical-align: top; padding: 0 8px; }
        .text { white-space: pre-wrap; word-wrap: break-word; }
        .total { margin-top: 24px; padding: 16px 20px; background: #324853; color: #fff; page-break-inside: avoid; }
        .total strong { display: block; font-size: 24px; font-weight: normal; }
        footer { position: fixed; bottom: -48px; left: 0; right: 0; border-top: 1px solid #d9dfdf; padding-top: 10px; color: #5d666c; font-size: 8px; }
    </style>
</head>
<body>
    <footer>Propuesta emitida tras evaluación comercial. Este documento y la confirmación del cliente no constituyen firma digital certificada.</footer>
    <table class="brand"><tr>
        <td><strong>{{ $quotation->studio_snapshot['name'] }}</strong><br><span class="muted">{{ $quotation->studio_snapshot['location'] }}</span></td>
        <td style="text-align:right"><img src="{{ $logo }}" alt="ARAT STUDIO"></td>
    </tr></table>
    <h1>Cotización oficial</h1>
    <p class="reference">{{ $quotation->code }} · versión {{ $quotation->version }}<br>Solicitud {{ $quotation->client_snapshot['request_code'] }} · revisión conservada</p>
    <table class="parties"><tr>
        <td><span class="muted">PREPARADA PARA</span><br><strong>{{ $quotation->client_snapshot['name'] }}</strong><br>{{ $quotation->client_snapshot['email'] }}<br>{{ $quotation->client_snapshot['phone'] }} · {{ $quotation->client_snapshot['city'] }}</td>
        <td><span class="muted">SERVICIO</span><br><strong>{{ $quotation->client_snapshot['service'] }}</strong><br>{{ $quotation->client_snapshot['project_type'] }}<br>Emisión {{ $quotation->issued_at->timezone('America/Lima')->format('d/m/Y H:i:s') }}<br>Vencimiento {{ $quotation->expires_at->timezone('America/Lima')->format('d/m/Y H:i:s') }}<br><span class="muted">Hora de Lima · vigencia de 15 días exactos</span></td>
    </tr></table>
    @foreach (['scope' => 'Alcance', 'deliverables' => 'Entregables', 'exclusions' => 'Exclusiones', 'execution_term' => 'Plazo de ejecución'] as $field => $label)
        <h2>{{ $label }}</h2><div class="text">{{ $quotation->$field ?? 'No registrado en versión heredada' }}</div>
    @endforeach
    <div class="total">Importe oficial<strong>{{ $quotation->currency }} {{ $quotation->official_amount }}</strong></div>
    @if ($quotation->adjustment_reason)<h2>Justificación del importe</h2><p class="text">{{ $quotation->adjustment_reason }}</p>@endif
</body>
</html>

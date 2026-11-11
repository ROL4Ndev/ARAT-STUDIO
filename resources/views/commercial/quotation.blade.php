@extends('layouts.app')
@section('title', 'Cotización '.$quotation->code.' | ARAT STUDIO')
@section('content')
<section class="workspace-view"><header class="workspace-hero"><div class="container"><h1>{{ $quotation->code }}</h1><p>Cotización oficial — {{ $quotation->isExpired() ? 'Vencida' : 'Vigente' }}</p><a href="{{ route('commercial.requests.show', $quotation->client_snapshot['request_code']) }}">Volver a la solicitud</a></div></header><div class="workspace-content"><div class="container"><div class="workspace-card">
@if(session('status'))<p class="alert alert-success">{{ session('status') }}</p>@endif
<h2>{{ $quotation->studio_snapshot['name'] }}</h2><p>{{ $quotation->studio_snapshot['location'] }}</p><p>Cliente: {{ $quotation->client_snapshot['name'] }} — {{ $quotation->client_snapshot['email'] }} — {{ $quotation->client_snapshot['phone'] }} — {{ $quotation->client_snapshot['city'] }}</p><p>Solicitud: {{ $quotation->client_snapshot['request_code'] }}</p><p>Servicio: {{ $quotation->client_snapshot['service'] }} — {{ $quotation->client_snapshot['project_type'] }}</p><h3>Alcance</h3><p style="white-space:pre-wrap">{{ $quotation->scope }}</p><p><strong>Importe oficial: {{ $quotation->currency }} {{ $quotation->official_amount }}</strong></p><p>Emisión: {{ $quotation->issued_at->format('d/m/Y') }} · Vencimiento: {{ $quotation->expires_at->format('d/m/Y') }}</p><p>Emitida por: {{ $quotation->user->name }}</p><a class="btn btn-dark" href="{{ route('commercial.quotations.download', $quotation) }}">Descargar PDF</a>
</div></div></div></section>
@endsection

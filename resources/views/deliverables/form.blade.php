@extends('layouts.app')
@section('title', 'Preparar entregable | ARAT STUDIO')
@section('content')
<section class="workspace-view" aria-labelledby="delivery-form-title">
    <header class="workspace-hero"><div class="container">
        <a href="{{ route('commercial.requests.show', $serviceRequest) }}#entregables">Volver al expediente {{ $serviceRequest->code }}</a>
        <h1 id="delivery-form-title">Preparar un entregable</h1>
        <p>El cierre comercial se conserva. La revisión de documentos tiene sus propios estados.</p>
    </div></header>
    <div class="workspace-content"><div class="container">
        @include('shared.errors')
        <form method="POST" action="{{ route('commercial.deliverables.store', $serviceRequest) }}" enctype="multipart/form-data" class="workspace-card">
            @csrf
            <label for="delivery-quotation" class="form-label">Cotización aceptada <span aria-hidden="true">*</span></label>
            <select id="delivery-quotation" name="quotation_code" class="form-select mb-4" required>
                @foreach ($accepted as $quotation)
                    <option value="{{ $quotation->code }}" @selected(old('quotation_code') === $quotation->code)>{{ $quotation->code }} · versión {{ $quotation->version }}</option>
                @endforeach
            </select>
            @error('quotation_code')<p class="form-feedback" role="alert">{{ $message }}</p>@enderror
            @include('deliverables.fields', ['version' => null])
            <button class="btn btn-dark mt-3" type="submit" data-loading-label="Guardando borrador…">Guardar borrador</button>
        </form>
    </div></div>
</section>
@endsection

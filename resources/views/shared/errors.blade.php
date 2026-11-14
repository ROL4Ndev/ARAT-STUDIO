@if ($errors->any())
    <div class="alert alert-danger" role="alert" data-validation-summary data-error-action="{{ old('action') }}" tabindex="-1">
        <p class="fw-semibold">Revisa estos datos para continuar:</p>
        <ul class="mb-0">
            @foreach ($errors->messages() as $field => $messages)
                @foreach ($messages as $message)<li data-field-error="{{ $field }}">{{ $message }}</li>@endforeach
            @endforeach
        </ul>
    </div>
@endif
@if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif

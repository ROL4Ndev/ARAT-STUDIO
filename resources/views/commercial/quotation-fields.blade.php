@foreach (['scope' => 'Alcance', 'deliverables' => 'Entregables', 'exclusions' => 'Exclusiones', 'execution_term' => 'Plazo de ejecución', 'adjustment_reason' => 'Justificación del importe (puede aparecer en el PDF para el cliente)', 'reason' => 'Motivo de la versión o edición'] as $field => $label)
    <label for="quote-{{ $field }}" class="form-label">{{ $label }}{{ $field === 'adjustment_reason' ? ' (opcional si el importe coincide con la estimación)' : '' }}@if ($field !== 'adjustment_reason') <span aria-hidden="true">*</span>@endif</label>
    <textarea
        id="quote-{{ $field }}"
        name="{{ $field }}"
        class="form-control mb-3 @error($field) is-invalid @enderror"
        rows="{{ $field === 'scope' ? 5 : 3 }}"
        @required($field !== 'adjustment_reason')
        maxlength="{{ $field === 'scope' ? 20000 : 5000 }}"
        @error($field) aria-invalid="true" aria-describedby="quote-{{ $field }}-error" @enderror
    >{{ old($field, $draft?->$field) }}</textarea>
    @error($field)
        <p id="quote-{{ $field }}-error" class="form-feedback" role="alert">{{ $message }}</p>
    @enderror
@endforeach

<label for="official_amount" class="form-label">Importe oficial positivo (PEN; puede diferir de la estimación) <span aria-hidden="true">*</span></label>
<input
    id="official_amount"
    name="official_amount"
    type="number"
    class="form-control mb-3 @error('official_amount') is-invalid @enderror"
    min="0.01"
    step="0.01"
    required
    value="{{ old('official_amount', $draft?->official_amount) }}"
    @error('official_amount') aria-invalid="true" aria-describedby="official-amount-error" @enderror
>
@error('official_amount')
    <p id="official-amount-error" class="form-feedback" role="alert">{{ $message }}</p>
@enderror

@extends('layouts.app')

@section('title', 'Cotizador referencial | ARAT STUDIO')

@section('content')
    <section class="workspace-view" aria-labelledby="calculator-title">
        <header class="workspace-hero">
            <div class="container">
                <span class="section-label">Planifica tu proyecto</span>

                <h1 id="calculator-title" class="workspace-hero__title mt-3">
                    Una primera idea,<br>
                    <em>con números claros.</em>
                </h1>

                <p class="workspace-hero__lead">
                    Ingresa el área y el nivel de desarrollo para obtener
                    una estimación referencial.
                </p>
            </div>
        </header>

        <div class="workspace-content">
            <div class="container">
                <div class="row g-4">
                    <div class="col-lg-7">
                        <form
                            id="quotation-form"
                            class="workspace-card"
                            data-endpoint="{{ route('quotation.calculate') }}"
                        >
                            <div class="workspace-card__heading">
                                <div>
                                    <h2>Datos del proyecto</h2>
                                    <p>El cálculo se actualiza al modificar los datos.</p>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="area_m2" class="workspace-label">
                                    Área aproximada en m²
                                </label>

                                <input
                                    id="area_m2"
                                    name="area_m2"
                                    type="number"
                                    class="form-control workspace-input"
                                    min="0.01"
                                    max="1000000"
                                    step="0.01"
                                    value="80"
                                    required
                                    aria-describedby="area-help"
                                >

                                <small id="area-help" class="workspace-help">
                                    Las áreas que superen el límite del nivel
                                    requieren evaluación personalizada.
                                </small>
                            </div>

                            <div class="mb-4">
                                <label for="lod_selected" class="workspace-label">
                                    Nivel de desarrollo
                                </label>

                                <select
                                    id="lod_selected"
                                    name="lod_selected"
                                    class="form-select workspace-input"
                                    required
                                >
                                    @forelse ($tariffs as $tariff)
                                        <option
                                            value="{{ $tariff->lod_level }}"
                                            @selected($tariff->lod_level === 'LOD_300')
                                        >
                                            {{ str_replace('_', ' ', $tariff->lod_level) }}
                                        </option>
                                    @empty
                                        <option value="">Sin tarifas disponibles</option>
                                    @endforelse
                                </select>
                            </div>

                            <div class="calculator-disclaimer mb-4">
                                <p>
                                    Tarifas de demostración. El resultado no
                                    constituye una cotización oficial.
                                </p>
                            </div>

                            <button
                                type="submit"
                                class="btn btn-dark"
                                @disabled($tariffs->isEmpty())
                            >
                                Calcular estimación
                            </button>
                        </form>
                    </div>

                    <div class="col-lg-5">
                        <aside
                            id="quotation-result"
                            class="estimate-card"
                            aria-labelledby="estimate-title"
                            aria-busy="false"
                        >
                            <h2 id="estimate-title" class="estimate-card__label">
                                Inversión referencial
                            </h2>

                            <output
                                id="quotation-amount"
                                class="estimate-card__amount"
                                for="area_m2 lod_selected"
                            >—</output>

                            <p
                                id="quotation-message"
                                class="estimate-card__message"
                                role="status"
                                aria-live="polite"
                            >
                                Completa los datos para calcular.
                            </p>

                            <div class="estimate-card__rule"></div>

                            <p class="estimate-card__footnote">
                                Moneda: soles peruanos. El equipo confirmará
                                el alcance y el presupuesto final.
                            </p>
                        </aside>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('js/calculator.js') }}" defer></script>
@endpush

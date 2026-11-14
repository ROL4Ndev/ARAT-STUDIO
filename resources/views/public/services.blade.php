@extends('layouts.app')

@section('title', 'Servicios | ARAT STUDIO')

@section('content')
    <section class="section-block section-block--gray public-info-page" aria-labelledby="services-title">
        <div class="container">
            <header class="section-header mb-5">
                <span class="section-label">Qué ofrecemos</span>
                <h1 id="services-title" class="section-title mt-2">Del diseño a la visualización.</h1>
            </header>
            @forelse ($services as $group => $serviceItems)
                <h2 class="services-group-label home-services-group">{{ $group }}</h2>
                <div class="row g-4 mb-5">
                    @foreach ($serviceItems as $service)
                        <div class="col-md-6 col-xl-3">
                            <article class="service-card">
                                <span class="service-card__number" aria-hidden="true">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                <h3 class="service-card__title">{{ $service->name }}</h3>
                                <p class="service-card__text">{{ $service->description }}</p>
                            </article>
                        </div>
                    @endforeach
                </div>
            @empty
                <p class="section-body">Estamos actualizando nuestro catálogo de servicios. Escríbenos para conversar sobre tu proyecto.</p>
            @endforelse
            <a class="btn btn-dark home-button" href="{{ route('service-requests.create') }}">Cuéntanos qué necesitas <span aria-hidden="true">→</span></a>
        </div>
    </section>
@endsection

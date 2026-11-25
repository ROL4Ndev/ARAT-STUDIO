@extends('layouts.app')

@section('title', 'Nosotros | ARAT STUDIO')

@section('content')
    <section class="section-block public-info-page" aria-labelledby="about-title">
        <div class="container">
            @include('public.partials.studio-intro', ['fullPage' => true])
        </div>
    </section>

    <section id="equipo" class="section-block section-block--gray" aria-labelledby="team-title">
        <div class="container">
            <header class="section-header mb-5">
                <span class="section-label">Quiénes somos</span>
                <h2 id="team-title" class="section-title mt-2">El equipo detrás del proyecto.</h2>
            </header>
            @include('public.partials.team')
        </div>
    </section>
@endsection

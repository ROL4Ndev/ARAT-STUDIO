@extends('layouts.app')
@section('title', 'Sesión vencida | ARAT STUDIO')
@section('content')
<section class="workspace-view"><div class="workspace-content"><div class="container"><div class="workspace-card"><h1>La sesión venció</h1><p>Abre de nuevo el formulario y revisa sus datos antes de enviarlo. No se confirmó ningún guardado con este envío.</p><a class="btn btn-dark" href="{{ route('home') }}">Volver al inicio</a></div></div></div></section>
@endsection

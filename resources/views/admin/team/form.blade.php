@extends('admin.layout')
@section('title', ($manager->exists ? 'Editar gestor' : 'Crear gestor') . ' | ARAT STUDIO')
@section('admin-title', $manager->exists ? 'Editar gestor' : 'Una nueva persona en el equipo')
@section('admin-content')
    @include('shared.errors')
    <form class="admin-form account-form" method="POST"
        action="{{ $manager->exists ? route('admin.team.update', $manager) : route('admin.team.store') }}">
        @csrf
        <input type="hidden" name="action" value="manager-save">
        @if ($manager->exists)
            @method('PUT')<input type="hidden" name="lock_version" value="{{ $manager->lock_version }}">
        @endif
        <p class="form-text">Esta cuenta tendrá acceso de gestor. Los permisos de administrador se gestionan fuera de este
            módulo.</p>
        <div>
            <label class="form-label" for="name">Nombre completo *</label>
            <input class="form-control" id="name" name="name" autocomplete="name"
                value="{{ old('name', $manager->name) }}" required maxlength="255">
        </div>
        <div>
            <label class="form-label" for="email">Correo electrónico *</label>
            <input class="form-control" id="email" name="email" type="email" autocomplete="email"
                value="{{ old('email', $manager->email) }}" required maxlength="255">
        </div>
        @unless ($manager->exists)
            <div>
                <label class="form-label" for="password">Contraseña inicial *</label>
                <input class="form-control" id="password" name="password" type="password" autocomplete="new-password" required
                    minlength="12" maxlength="255">
                <p class="form-text">Al menos 12 caracteres. No la incluyas en el motivo del alta.</p>
            </div>
            <div>
                <label class="form-label" for="password_confirmation">Confirmar contraseña *</label>
                <input class="form-control" id="password_confirmation" name="password_confirmation" type="password"
                    autocomplete="new-password" required minlength="12" maxlength="255">
            </div>
        @endunless
        <div>
            <label class="form-label" for="reason">Motivo del {{ $manager->exists ? 'cambio' : 'alta' }} *</label>
            <textarea class="form-control" id="reason" name="reason" required minlength="5" maxlength="2000">{{ old('reason') }}</textarea>
        </div>
        <div class="d-flex flex-wrap gap-3">
            <button class="btn btn-dark" type="submit">{{ $manager->exists ? 'Guardar datos' : 'Crear gestor' }}</button>
            <a class="btn btn-outline-dark" href="{{ route('admin.team.index') }}">Volver al equipo</a>
        </div>
    </form>
@endsection

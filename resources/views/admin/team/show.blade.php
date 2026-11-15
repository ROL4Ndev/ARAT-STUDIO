@extends('admin.layout')
@section('title', $manager->name . ' · Equipo | ARAT STUDIO')
@section('admin-title', $manager->name)
@section('admin-content')
    @include('shared.errors')
    <div class="admin-toolbar">
        <p>{{ $manager->email }} · @include('shared.status-label', ['value' => $manager->is_active ? 'activo' : 'inactivo'])</p>
        <a class="btn btn-dark" href="{{ route('admin.team.edit', $manager) }}">Editar datos</a>
    </div>
    <section class="workspace-card mb-4" aria-labelledby="assigned-title">
        <h2 id="assigned-title">Expedientes asignados</h2>
        <p>
            <strong>{{ $openCount }}</strong> abiertos. Las relaciones de expedientes cerrados se conservan.
        </p>
        <div class="table-responsive" tabindex="0" role="region" aria-label="Expedientes del gestor">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Expediente</th>
                        <th scope="col">Servicio</th>
                        <th scope="col">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $item)
                        <tr>
                            <td>
                                <a href="{{ route('commercial.requests.show', $item) }}">{{ $item->code }}</a>
                            </td>
                            <td>{{ $item->service }}</td>
                            <td>
                                @include('shared.status-label', ['value' => $item->status])</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">Todavía no tiene expedientes asignados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>{{ $requests->links() }}
    </section>
    <section class="workspace-card mb-4" aria-labelledby="manager-status-title">
        <h2 id="manager-status-title">{{ $manager->is_active ? 'Desactivar cuenta' : 'Reactivar cuenta' }}</h2>
        <p>{{ $manager->is_active ? 'El gestor perderá acceso. Antes de desactivar, sus expedientes abiertos se reasignarán al responsable que elijas, en la misma operación.' : 'La cuenta podrá iniciar una nueva sesión. Los expedientes y decisiones anteriores conservarán su historial.' }}
        </p>
        <form method="POST" action="{{ route('admin.team.status', $manager) }}"
            data-confirm="¿Confirmas {{ $manager->is_active ? 'reasignar los expedientes abiertos y desactivar esta cuenta' : 'reactivar esta cuenta' }}?">
            @csrf
            <input type="hidden" name="lock_version" value="{{ $manager->lock_version }}">
            <input type="hidden" name="expected_open_count" value="{{ $openCount }}">
            <input type="hidden" name="action" value="manager-status">
            <input type="hidden" name="is_active" value="{{ $manager->is_active ? 0 : 1 }}">
            @if ($manager->is_active && $openCount)
                <label class="form-label" for="replacement_id">Nuevo responsable de los {{ $openCount }} expedientes
                    abiertos *</label>
                <select class="form-select" id="replacement_id" name="replacement_id" required>
                    <option value="">Seleccionar responsable</option>
                    @foreach ($replacements as $replacement)
                        <option value="{{ $replacement->id }}" @selected((string) old('replacement_id') === (string) $replacement->id)>{{ $replacement->name }}
                        </option>
                    @endforeach
                </select>
            @endif
            <label class="form-label" for="status_reason">Motivo *</label>
            <textarea class="form-control" id="status_reason" name="reason" required minlength="5" maxlength="2000">{{ old('reason') }}</textarea>
            <button class="btn btn-outline-dark mt-3"
                type="submit">{{ $manager->is_active ? ($openCount ? 'Reasignar y desactivar' : 'Desactivar cuenta') : 'Reactivar cuenta' }}</button>
        </form>
    </section>
    <section class="workspace-card mb-4">
        <details class="secondary-details" @if ($errors->has('password') || $errors->has('current_password')) open @endif>
            <summary>Restablecer contraseña del gestor</summary>
            <p>La nueva contraseña sustituirá a la anterior y cerrará el acceso de las sesiones previas. No se mostrará
                después de guardarla.</p>
            <form class="account-form" method="POST" action="{{ route('admin.team.password', $manager) }}"
                data-confirm="¿Confirmas restablecer la contraseña e invalidar las sesiones anteriores de este gestor?">
                @csrf
                    <input type="hidden" name="lock_version" value="{{ $manager->lock_version }}">
                    <input type="hidden" name="action" value="manager-password">
                <div>
                    <label class="form-label" for="current_password">Tu contraseña de administrador *</label>
                    <input class="form-control" id="current_password" type="password" name="current_password"
                        autocomplete="current-password" required>
                </div>
                <div>
                    <label class="form-label" for="password">Nueva contraseña del gestor *</label>
                    <input class="form-control" id="password" type="password" name="password" autocomplete="new-password"
                        required minlength="12" maxlength="255">
                </div>
                <div>
                    <label class="form-label" for="password_confirmation">Confirmar nueva contraseña *</label>
                    <input class="form-control" id="password_confirmation" type="password" name="password_confirmation"
                        autocomplete="new-password" required minlength="12" maxlength="255">
                </div>
                <div>
                    <label class="form-label" for="password_reason">Motivo del restablecimiento *</label>
                    <textarea class="form-control" id="password_reason" name="reason" required minlength="5" maxlength="2000">{{ old('reason') }}</textarea>
                </div>
                <button class="btn btn-outline-dark" type="submit">Restablecer contraseña</button>
            </form>
        </details>
    </section>
    <section class="workspace-card">
        <h2>Historial de la cuenta</h2>
        <ol class="history-timeline">
            @forelse($events as $event)
                <li>
                    <time>{{ $event->occurred_at->timezone('America/Lima')->format('d/m/Y H:i') }} ·
                        Lima</time>
                    @include('shared.activity-label', ['value' => $event->action]) · {{ $event->reason }}
                </li>
            @empty
                <li>No hay cambios
                    registrados desde este módulo.</li>
            @endforelse
        </ol>{{ $events->links() }}
    </section>
@endsection

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ManagerRequest;
use App\Models\AuditEvent;
use App\Models\User;
use App\Services\TeamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', User::class);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:150'], 'active' => ['nullable', 'in:0,1']]);
        $managers = User::where('role', 'gestor')
            ->when($filters['q'] ?? null, fn ($query, $text) => $query->where(fn ($search) => $search->where('name', 'like', '%'.$text.'%')->orWhere('email', 'like', '%'.$text.'%')))
            ->when(isset($filters['active']), fn ($query) => $query->where('is_active', $filters['active']))
            ->withCount(['assignedRequests as open_requests_count' => fn ($query) => $query->whereNotIn('status', ['cerrada', 'cancelado', 'rechazado'])])
            ->orderBy('name')->paginate(15)->withQueryString();

        return view('admin.team.index', compact('managers'));
    }

    public function create()
    {
        Gate::authorize('create', User::class);

        return view('admin.team.form', ['manager' => new User]);
    }

    public function show(User $manager)
    {
        Gate::authorize('view', $manager);
        $openCount = $manager->assignedRequests()->whereNotIn('status', ['cerrada', 'cancelado', 'rechazado'])->count();
        $requests = $manager->assignedRequests()->latest('id')->paginate(10)->withQueryString();
        $replacements = User::where('is_active', true)->whereIn('role', ['admin', 'gestor'])->whereKeyNot($manager->id)->orderBy('name')->get(['id', 'name']);
        $events = AuditEvent::where('resource_type', $manager->getMorphClass())->where('resource_id', $manager->id)->latest('id')->paginate(10, ['*'], 'audit_page');

        return view('admin.team.show', compact('manager', 'openCount', 'requests', 'replacements', 'events'));
    }

    public function edit(User $manager)
    {
        Gate::authorize('update', $manager);

        return view('admin.team.form', compact('manager'));
    }

    public function store(ManagerRequest $request, TeamService $team)
    {
        $manager = $team->save(null, $request->user(), $request->validated());

        return redirect()->route('admin.team.show', $manager)->with('status', 'Gestor creado. Comunica su acceso por un canal privado.');
    }

    public function update(ManagerRequest $request, User $manager, TeamService $team)
    {
        $team->save($manager, $request->user(), $request->validated());

        return redirect()->route('admin.team.show', $manager)->with('status', 'Datos del gestor actualizados.');
    }

    public function status(ManagerRequest $request, User $manager, TeamService $team)
    {
        $team->changeStatus($manager, $request->user(), $request->validated());

        return redirect()->route('admin.team.show', $manager)->with('status', $request->boolean('is_active') ? 'Cuenta activada. El gestor puede iniciar una nueva sesión.' : 'Cuenta desactivada. Se conservaron su historial y sus relaciones; los expedientes abiertos quedaron reasignados.');
    }

    public function password(ManagerRequest $request, User $manager, TeamService $team)
    {
        $team->resetPassword($manager, $request->user(), $request->validated());

        return redirect()->route('admin.team.show', $manager)->with('status', 'Contraseña restablecida y sesiones anteriores invalidadas. Comunica el nuevo acceso por un canal privado.');
    }
}

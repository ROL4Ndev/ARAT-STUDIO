<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        return match ($request->user()->role) {
            'admin' => redirect()->route('admin.dashboard'),
            'gestor' => redirect()->route('management.dashboard'),
            default => abort(403),
        };
    }

    public function admin(): View
    {
        return view('panels.dashboard', [
            'panel' => 'Administración',
            'role' => 'Administrador',
        ]);
    }

    public function management(): View
    {
        return view('panels.dashboard', [
            'panel' => 'Gestión',
            'role' => auth()->user()->role === 'admin' ? 'Administrador' : 'Gestor',
        ]);
    }
}

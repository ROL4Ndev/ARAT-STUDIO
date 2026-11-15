<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        // Las sesiones previas a esta versión solo se conservan si nunca se revocó la cuenta.
        $sessionVersion = $request->session()->get('auth_version', 0);
        if ($user && (! $user->is_active || $sessionVersion !== $user->auth_version)) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            abort(403, 'El acceso de esta cuenta cambió. Inicia sesión de nuevo o consulta al administrador.');
        }

        return $next($request);
    }
}

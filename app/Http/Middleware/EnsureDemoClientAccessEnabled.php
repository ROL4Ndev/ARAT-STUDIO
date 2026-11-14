<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDemoClientAccessEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('gestion/solicitudes/*/acceso-demo')) {
            return $next($request);
        }

        abort_unless(config('app.env') === 'local' && config('client_access.demo_enabled'), 404);

        return $next($request);
    }
}

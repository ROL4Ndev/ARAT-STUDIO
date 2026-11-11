<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless(
            $request->user() !== null && in_array($request->user()->role, $roles, true),
            Response::HTTP_FORBIDDEN,
        );

        return $next($request);
    }
}

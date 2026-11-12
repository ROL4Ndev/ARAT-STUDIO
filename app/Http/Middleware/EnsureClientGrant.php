<?php

namespace App\Http\Middleware;

use App\Services\ClientAccessService;
use Closure;
use Illuminate\Http\Request;

class EnsureClientGrant
{
    public function handle(Request $request, Closure $next)
    {
        $request->attributes->set('clientGrant', app(ClientAccessService::class)->grant($request));
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}

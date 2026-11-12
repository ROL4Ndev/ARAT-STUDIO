<?php

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [EnsureActiveUser::class]);
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('solicitar-proyecto', 'acceder-expediente', 'acceso-expediente/*', 'mi-solicitud/*')) {
                return response()->view('errors.too-many-requests', [], 429, $e->getHeaders());
            }
        });
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            if ($request->is('solicitar-proyecto', 'mi-solicitud/corregir')) {
                return response()->view('errors.upload-too-large', [], 413);
            }
        });
    })->create();

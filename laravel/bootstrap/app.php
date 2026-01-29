<?php

use App\Http\Middleware\CheckAnyRole;
use App\Http\Middleware\CheckLogs;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\IsAdmin;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
        'IsAdmin' => IsAdmin::class,
        'role'    => CheckRole::class,
        'roles'   => CheckAnyRole::class,
        'activity' => CheckLogs::class,
    ]);
        $middleware->prepend(CheckLogs::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle unauthenticated requests
      $exceptions->render(function (AuthenticationException $e, $request) {
            return response()->json([
                'message' => 'You are not authenticated.',
            ], 401);
        });

        // Handle missing "login" route
        $exceptions->render(function (RouteNotFoundException $e, $request) {
            return response()->json([
                'message' => 'you are not authorized to access',
            ], 401);
        });

    })->create();

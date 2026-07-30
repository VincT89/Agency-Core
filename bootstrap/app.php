<?php

use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\N8nAuth;
use App\Http\Middleware\N8nIdempotency;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'force.password.change' => ForcePasswordChange::class,
            'n8n.auth' => N8nAuth::class,
            'n8n.idempotency' => N8nIdempotency::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

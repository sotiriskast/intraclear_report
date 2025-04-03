<?php

use App\Http\Middleware\DashboardApiAuthentication;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'merchant.active' => \App\Http\Middleware\EnsureMerchantIsActive::class,
            '2fa.required' => \App\Http\Middleware\ForceTwoFactor::class,

        ]);
        // Add API middleware group
        $middleware->group('api', [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class . ':api',
            \App\Http\Middleware\ForceJsonResponse::class,
        ]);

        $middleware->group('dashboard-api', [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            DashboardApiAuthentication::class,
            SubstituteBindings::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {

    })->create();

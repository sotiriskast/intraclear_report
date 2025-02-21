<?php

namespace App\Providers;

use App\Http\Middleware\CheckRole;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\ServiceProvider;

class MiddlewareProvider extends ServiceProvider
{
    public function boot(): void
    {
        $router = $this->app['router'];
        $router->aliasMiddleware('role', CheckRole::class);
        // Add middleware group for API
        $router->aliasMiddleware('throttle', ThrottleRequests::class);
        $router->middlewareGroup('api', [
            'throttle:api',
            SubstituteBindings::class,
        ]);
    }
}

<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Http\Middleware\CheckRole;

class MiddlewareProvider extends ServiceProvider
{
    public function boot(): void
    {
        $router = $this->app['router'];
        $router->aliasMiddleware('role', CheckRole::class);
    }
}

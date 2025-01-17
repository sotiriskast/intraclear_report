<?php

namespace App\Providers;

use App\Repositories\RoleRepository;
use App\Services\DynamicLogger;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(RoleRepository::class, function ($app) {
            return new RoleRepository();
        });
        $this->app->singleton(DynamicLogger::class, function ($app) {
            return new DynamicLogger();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

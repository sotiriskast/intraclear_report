<?php

namespace App\Providers;

use App\Repositories\RoleRepository;
use App\Services\DynamicLogger;
use App\Services\ExcelExportService;
use App\Services\SettlementService;
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
        $this->app->bind(
            \App\Repositories\Interfaces\FeeRepositoryInterface::class,
            \App\Repositories\FeeRepository::class
        );

        $this->app->bind(
            \App\Repositories\Interfaces\RollingReserveRepositoryInterface::class,
            \App\Repositories\RollingReserveRepository::class
        );

        $this->app->bind(
            \App\Repositories\Interfaces\TransactionRepositoryInterface::class,
            \App\Repositories\TransactionRepository::class
        );

        // Register services as singletons
        $this->app->singleton(SettlementService::class);
        $this->app->singleton(ExcelExportService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

<?php
namespace App\Providers;

use App\Api\V1\Services\RollingReserve\RollingReserveApiService;
use Illuminate\Support\ServiceProvider;

class ApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RollingReserveApiService::class);
    }

    public function boot(): void
    {
        // API specific configurations
    }
}

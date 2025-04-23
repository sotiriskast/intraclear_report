<?php

namespace App\Providers;

use App\Console\Commands\AddPermission;
use App\Console\Commands\GenerateSettlementReports;
use App\Console\Commands\ImportMerchants;
use App\Exceptions\ApiExceptionHandler;
use App\Exceptions\Handler;
use App\Repositories\ChargebackTrackingRepository;
use App\Repositories\FeeRepository;
use App\Repositories\Interfaces\ChargebackTrackingRepositoryInterface;
use App\Repositories\Interfaces\TransactionRepositoryInterface;
use App\Repositories\MerchantRepository;
use App\Repositories\MerchantSettingRepository;
use App\Repositories\RoleRepository;
use App\Repositories\RollingReserveRepository;
use App\Repositories\TransactionRepository;
use App\Services\DynamicLogger;
use App\Services\ExcelExportService;
use App\Services\MerchantSyncService;
use App\Services\Settlement\Chargeback\ChargebackProcessor;
use App\Services\Settlement\Chargeback\ChargebackSettlementProcessor;
use App\Services\Settlement\Chargeback\Interfaces\ChargebackProcessorInterface;
use App\Services\Settlement\Chargeback\Interfaces\ChargebackSettlementInterface;
use App\Services\Settlement\Fee\CustomFeeHandler;
use App\Services\Settlement\Fee\FeeFrequencyHandler;
use App\Services\Settlement\Fee\FeeService;
use App\Services\Settlement\Fee\StandardFeeHandler;
use App\Services\Settlement\Reserve\RollingReserveHandler;
use App\Services\Settlement\SchemeRateValidationService;
use App\Services\Settlement\SettlementService;
use App\Services\ZipExportService;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /**
         * ------------------------------------------------
         * Repositories
         * ------------------------------------------------
         */
        $this->app->bind(RoleRepository::class, function () {
            return new RoleRepository;
        });

        $this->app->bind(
            \App\Repositories\Interfaces\FeeRepositoryInterface::class,
            FeeRepository::class
        );

        $this->app->bind(
            \App\Repositories\Interfaces\RollingReserveRepositoryInterface::class,
            RollingReserveRepository::class
        );

        $this->app->bind(
            TransactionRepositoryInterface::class,
            TransactionRepository::class
        );

        $this->app->bind(
            ChargebackTrackingRepositoryInterface::class,
            ChargebackTrackingRepository::class
        );

        /**
         * ------------------------------------------------
         * Services
         * ------------------------------------------------
         */
        $this->app->singleton(DynamicLogger::class, function () {
            return new DynamicLogger;
        });

        $this->app->bind(
            \App\Services\Settlement\Fee\interfaces\FeeFrequencyHandlerInterface::class,
            FeeFrequencyHandler::class
        );

        $this->app->bind(
            \App\Services\Settlement\Fee\interfaces\CustomFeeHandlerInterface::class,
            CustomFeeHandler::class
        );

        $this->app->bind(
            \App\Services\Settlement\Fee\interfaces\StandardFeeHandlerInterface::class,
            StandardFeeHandler::class
        );

        $this->app->bind(
            ChargebackProcessorInterface::class,
            ChargebackProcessor::class
        );

        $this->app->bind(
            ChargebackSettlementInterface::class,
            ChargebackSettlementProcessor::class
        );

        /**
         * ------------------------------------------------
         * Singleton Services
         * ------------------------------------------------
         */

        $this->app->singleton(ExcelExportService::class);

        // Replace the simple singleton with a proper binding for SettlementService
        $this->app->singleton(SettlementService::class, function ($app) {
            return new SettlementService(
                $app->make(TransactionRepositoryInterface::class),
                $app->make(ChargebackSettlementInterface::class),
                $app->make(RollingReserveHandler::class),
                $app->make(DynamicLogger::class),
                $app->make(FeeService::class),
                $app->make(SchemeRateValidationService::class),
            );
        });
        $this->app->singleton(SchemeRateValidationService::class, function ($app) {
            return new SchemeRateValidationService($app->make(DynamicLogger::class));
        });
        /**
         * ------------------------------------------------
         * Command Bindings
         * ------------------------------------------------
         */
        $this->app->singleton(GenerateSettlementReports::class, function ($app) {
            return new GenerateSettlementReports(
                $app->make(SettlementService::class),
                $app->make(ExcelExportService::class),
                $app->make(ZipExportService::class),
                $app->make(DynamicLogger::class),
            );
        });
        $this->app->singleton(ImportMerchants::class, function ($app) {
            return new ImportMerchants(
                $app->make(DynamicLogger::class),
                $app->make(MerchantSyncService::class)
            );
        });
        $this->app->singleton(AddPermission::class, function ($app) {
            return new AddPermission(
                $app->make(DynamicLogger::class),
            );
        });

        /**
         * ------------------------------------------------
         * FeeService Binding
         * ------------------------------------------------
         */
        $this->app->singleton(FeeService::class, function ($app) {
            return new FeeService(
                $app->make(FeeRepository::class),
                $app->make(DynamicLogger::class),
                $app->make(FeeFrequencyHandler::class),
                $app->make(CustomFeeHandler::class),
                $app->make(StandardFeeHandler::class),
                $app->make(MerchantRepository::class),
                $app->make(MerchantSettingRepository::class),
            );
        });

        $this->app->singleton(ApiExceptionHandler::class, function ($app) {
            return new ApiExceptionHandler($app->make(DynamicLogger::class));
        });

        $this->app->singleton(
            ExceptionHandler::class,
            Handler::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (env('APP_USE_HTTPS') == 'dev') {
            URL::forceScheme('https');
        }
        Model::shouldBeStrict(!$this->app->isProduction());
        DB::prohibitDestructiveCommands($this->app->isProduction());
    }
}

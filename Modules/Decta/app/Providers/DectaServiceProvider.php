<?php

namespace Modules\Decta\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Modules\Decta\Console\DectaBulkHistoricalImportCommand;
use Modules\Decta\Console\DectaFixFilePathsCommand;
use Modules\Decta\Console\DectaNotificationStatusCommand;
use Modules\Decta\Console\DectaTestConnectionCommand;
use Modules\Decta\Console\DectaTestDeclinedNotificationCommand;
use Modules\Decta\Console\DectaTestIntegrationCommand;
use Modules\Decta\Console\DectaTestLatestFileCommand;
use Modules\Decta\Console\DectaMatchTransactionsCommand;
use Modules\Decta\Console\DectaCleanupCommand;
use Modules\Decta\Console\DectaStatusCommand;
use Modules\Decta\Console\DectaTestNotificationCommand;
use Modules\Decta\Console\DectaSetupCommand;
use Modules\Decta\Console\DectaCheckDeclinedTransactionsCommand; // New command
use Modules\Decta\Services\DectaExportService;
use Nwidart\Modules\Traits\PathNamespace;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Decta\Console\DectaDownloadFilesCommand;
use Modules\Decta\Console\DectaProcessFilesCommand;
use Modules\Decta\Services\DectaSftpService;
use Modules\Decta\Services\DectaTransactionService;
use Modules\Decta\Services\DectaReportService;
use Modules\Decta\Services\DectaNotificationService;
use Modules\Decta\Repositories\DectaFileRepository;
use Modules\Decta\Repositories\DectaTransactionRepository;

class DectaServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'Decta';

    protected string $nameLower = 'decta';

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->registerCommands();
        $this->registerCommandSchedules();
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->register(EventServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);

        // Register the SFTP service
        $this->app->bind(DectaSftpService::class, function ($app) {
            return new DectaSftpService();
        });

        // Register the file repository
        $this->app->bind(DectaFileRepository::class, function ($app) {
            return new DectaFileRepository();
        });

        // Register the transaction repository
        $this->app->bind(DectaTransactionRepository::class, function ($app) {
            return new DectaTransactionRepository();
        });

        // Register the notification service
        $this->app->bind(DectaNotificationService::class, function ($app) {
            return new DectaNotificationService();
        });

        // Register the transaction service
        $this->app->bind(DectaTransactionService::class, function ($app) {
            return new DectaTransactionService(
                $app->make(DectaTransactionRepository::class)
            );
        });

        // Register the report service
        $this->app->bind(DectaReportService::class, function ($app) {
            return new DectaReportService();
        });
        $this->app->bind(DectaExportService::class, function ($app) {
            return new DectaExportService();
        });
    }

    /**
     * Register commands in the format of Command::class
     */
    protected function registerCommands(): void
    {
        $this->commands([
            DectaDownloadFilesCommand::class,
            DectaProcessFilesCommand::class,
            DectaMatchTransactionsCommand::class,
            DectaCleanupCommand::class,
            DectaStatusCommand::class,
            DectaTestConnectionCommand::class,
            DectaTestLatestFileCommand::class,
            DectaFixFilePathsCommand::class,
            DectaTestNotificationCommand::class,
            DectaSetupCommand::class,
            DectaBulkHistoricalImportCommand::class,
            DectaCheckDeclinedTransactionsCommand::class,
            DectaTestDeclinedNotificationCommand::class,
            DectaTestIntegrationCommand::class,
            DectaNotificationStatusCommand::class,
        ]);
    }

    /**
     * Register command Schedules.
     */
    protected function registerCommandSchedules(): void
    {
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);

            // Schedule the download command to run every day at 2 AM for yesterday's files
            $schedule->command('decta:download-files')
                ->dailyAt('02:00')
                ->withoutOverlapping(1440) // 24 hours overlap protection
                ->appendOutputTo(storage_path('logs/decta-download.log'));

            // Schedule the process command to run every day at 3 AM
            $schedule->command('decta:process-files')
                ->dailyAt('03:00')
                ->withoutOverlapping(1440) // 24 hours overlap protection
                ->appendOutputTo(storage_path('logs/decta-process.log'));

            // Schedule retry of failed processing every 6 hours
            $schedule->command('decta:process-files --retry-failed --limit=10')
                ->everySixHours()
                ->withoutOverlapping(360) // 6 hours overlap protection
                ->appendOutputTo(storage_path('logs/decta-retry.log'));

            // Schedule transaction matching retry every 4 hours
            $schedule->command('decta:match-transactions --retry-failed --limit=50')
                ->cron('0 */4 * * *') // Every 4 hours
                ->withoutOverlapping(240) // 4 hours overlap protection
                ->appendOutputTo(storage_path('logs/decta-matching.log'));

            // Schedule cleanup of old records every Sunday at 1 AM
            $schedule->command('decta:cleanup --days-old=90 --remove-processed')
                ->weekly()
                ->sundays()
                ->at('01:00')
                ->appendOutputTo(storage_path('logs/decta-cleanup.log'));

            // NEW: Schedule declined transactions check every day at 9 AM
            $schedule->command('decta:check-declined-transactions')
                ->dailyAt('08:00')
                ->withoutOverlapping(1440) // 24 hours overlap protection
                ->appendOutputTo(storage_path('logs/decta-declined-check.log'));
        });
    }

    /**
     * Register translations.
     */
    public function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/'.$this->nameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->nameLower);
            $this->loadJsonTranslationsFrom($langPath);
        } else {
            $this->loadTranslationsFrom(module_path($this->name, 'lang'), $this->nameLower);
            $this->loadJsonTranslationsFrom(module_path($this->name, 'lang'));
        }
    }

    /**
     * Register config.
     */
    protected function registerConfig(): void
    {
        $this->publishes([
            module_path($this->name, 'config/config.php') => config_path($this->nameLower . '.php'),
        ], 'config');
        $this->mergeConfigFrom(
            module_path($this->name, 'config/config.php'), $this->nameLower
        );
    }

    /**
     * Register views.
     */
    public function registerViews(): void
    {
        $viewPath = resource_path('views/modules/'.$this->nameLower);
        $sourcePath = module_path($this->name, 'resources/views');

        $this->publishes([$sourcePath => $viewPath], ['views', $this->nameLower.'-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->nameLower);

        Blade::componentNamespace(config('modules.namespace').'\\' . $this->name . '\\View\\Components', $this->nameLower);
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            DectaSftpService::class,
            DectaFileRepository::class,
            DectaTransactionRepository::class,
            DectaTransactionService::class,
            DectaReportService::class,
            DectaNotificationService::class,
            DectaExportService::class,
        ];
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (config('view.paths') as $path) {
            if (is_dir($path.'/modules/'.$this->nameLower)) {
                $paths[] = $path.'/modules/'.$this->nameLower;
            }
        }

        return $paths;
    }
}

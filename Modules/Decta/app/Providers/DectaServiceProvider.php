<?php

namespace Modules\Decta\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Modules\Decta\Console\DectaTestConnectionCommand;
use Modules\Decta\Console\DectaTestLatestFileCommand;
use Nwidart\Modules\Traits\PathNamespace;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Decta\Console\DectaDownloadFilesCommand;
use Modules\Decta\Console\DectaProcessFilesCommand;
use Modules\Decta\Services\DectaSftpService;
use Modules\Decta\Repositories\DectaFileRepository;

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
    }

    /**
     * Register commands in the format of Command::class
     */
    protected function registerCommands(): void
    {
        $this->commands([
            DectaDownloadFilesCommand::class,
            DectaProcessFilesCommand::class,
            DectaTestConnectionCommand::class,
            DectaTestLatestFileCommand::class,
        ]);
    }

    /**
     * Register command Schedules.
     */
    protected function registerCommandSchedules(): void
    {
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);

            // Schedule the download command to run every hour
            $schedule->command('decta:download-files')
                ->hourly()
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/decta-download.log'));

            // Schedule the process command to run every hour, 15 minutes after download
            $schedule->command('decta:process-files')
                ->hourly()
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/decta-process.log'));
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

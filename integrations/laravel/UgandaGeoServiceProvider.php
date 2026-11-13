<?php

declare(strict_types=1);

namespace Uganda\Laravel;

use Illuminate\Support\ServiceProvider;
use Uganda\Laravel\Commands\DiscoverCommand;
use Uganda\Laravel\Commands\SetupCommand;
use Uganda\Laravel\Commands\StatusCommand;
use Uganda\Laravel\Commands\SyncCommand;

final class UgandaGeoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__, 2) . '/config/uganda-geo.php', 'uganda-geo');
    }

    public function boot(): void
    {
        $this->publishes([
            dirname(__DIR__, 2) . '/config/uganda-geo.php' => config_path('uganda-geo.php'),
        ], 'uganda-geo-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                DiscoverCommand::class,
                SetupCommand::class,
                SyncCommand::class,
                StatusCommand::class,
            ]);
        }
    }
}

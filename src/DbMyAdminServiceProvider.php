<?php

namespace LucaPellegrino\DbMyAdmin;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;
use LucaPellegrino\DbMyAdmin\Contracts\ActiveConnectionLabelProvider;
use LucaPellegrino\DbMyAdmin\Contracts\ActiveConnectionResolver;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
use LucaPellegrino\DbMyAdmin\Support\ConnectionManager;
use LucaPellegrino\DbMyAdmin\Support\NullConnectionResolver;

class DbMyAdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/dbmyadmin.php', 'dbmyadmin');

        $this->app->bind(ActiveConnectionResolver::class, NullConnectionResolver::class);

        $this->app->bind(ActiveConnectionLabelProvider::class, \LucaPellegrino\DbMyAdmin\Support\NullConnectionLabelProvider::class);

        $this->app->singleton(DatabaseDriver::class, function ($app) {
            $configured = config('dbmyadmin.driver', 'auto');
            $driver = $configured === 'auto'
                ? ConnectionManager::connection()->getDriverName()
                : $configured;

            $map = config('dbmyadmin.drivers', []);

            if (! isset($map[$driver])) {
                throw new \RuntimeException(
                    "DbMyAdmin: unsupported database driver [{$driver}]. Supported: " . implode(', ', array_keys($map)) . "."
                );
            }

            return $app->make($map[$driver]);
        });
    }

    public function boot(): void
    {
        FilamentAsset::register([
            Css::make('dbmyadmin-styles', __DIR__ . '/../dist/dbmyadmin.css'),
        ], package: 'lucapellegrino/dbmyadmin');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'dbmyadmin');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/dbmyadmin.php' => config_path('dbmyadmin.php'),
            ], 'dbmyadmin-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'dbmyadmin-migrations');
        }
    }
}

<?php

namespace LucaPellegrino\DbMyAdmin;

use Illuminate\Support\ServiceProvider;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
use LucaPellegrino\DbMyAdmin\Drivers\MySqlDriver;
use LucaPellegrino\DbMyAdmin\Drivers\PostgresDriver;
use LucaPellegrino\DbMyAdmin\Drivers\SqliteDriver;

class DbMyAdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/dbmyadmin.php', 'dbmyadmin');

        $this->app->singleton(DatabaseDriver::class, function ($app) {
            $configured = config('dbmyadmin.driver', 'auto');
            $driver = $configured === 'auto'
                ? $app['db']->connection()->getDriverName()
                : $configured;

            return match ($driver) {
                'mysql', 'mariadb' => new MySqlDriver(),
                'pgsql'            => new PostgresDriver(),
                'sqlite'           => new SqliteDriver(),
                default            => throw new \RuntimeException(
                    "DbMyAdmin: unsupported database driver [{$driver}]. Supported: mysql, pgsql, sqlite."
                ),
            };
        });
    }

    public function boot(): void
    {
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

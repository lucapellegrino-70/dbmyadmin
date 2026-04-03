<?php

namespace LucaPellegrino\DbMyAdmin\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Panel;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use LucaPellegrino\DbMyAdmin\DbMyAdminPlugin;
use LucaPellegrino\DbMyAdmin\DbMyAdminServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Filament's SupportServiceProvider registers DataStore as a non-singleton
        // bind(), which drops Livewire's singleton. Re-register it as a singleton
        // after all providers have booted so store()->set() and store()->get()
        // share the same WeakMap instance within each test.
        $this->app->singleton(
            \Livewire\Mechanisms\DataStore::class,
            \Filament\Support\Livewire\Partials\DataStoreOverride::class,
        );

        // Register a default Filament panel so Page components can render in tests.
        $panel = Panel::make()
            ->id('test')
            ->default()
            ->plugin(DbMyAdminPlugin::make());

        filament()->registerPanel($panel);
    }

    protected function getPackageProviders($app): array
    {
        return [
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            LivewireServiceProvider::class,
            SupportServiceProvider::class,
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentServiceProvider::class,
            DbMyAdminServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('session.driver', 'array');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}

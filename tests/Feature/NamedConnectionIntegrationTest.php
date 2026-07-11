<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LucaPellegrino\DbMyAdmin\Contracts\ActiveConnectionResolver;
use LucaPellegrino\DbMyAdmin\Models\DatabaseTable;
use LucaPellegrino\DbMyAdmin\Support\ConnectionManager;

beforeEach(function () {
    $this->secondaryDbPath = sys_get_temp_dir() . '/dbmyadmin_e2e_' . uniqid() . '.sqlite';
    touch($this->secondaryDbPath);

    config([
        'database.connections.dbmyadmin_e2e_secondary' => [
            'driver'   => 'sqlite',
            'database' => $this->secondaryDbPath,
            'prefix'   => '',
        ],
    ]);

    Schema::connection('dbmyadmin_e2e_secondary')->create('secondary_only_table', function ($table) {
        $table->id();
    });
});

afterEach(function () {
    ConnectionManager::reset();
    DatabaseTable::clearCache();
    config(['dbmyadmin.connection' => null]);
    DB::purge('dbmyadmin_e2e_secondary');
    @unlink($this->secondaryDbPath);
});

it('lists tables from the connection an ActiveConnectionResolver activates, not the app default', function () {
    app()->bind(ActiveConnectionResolver::class, function () {
        return new class ($this) implements ActiveConnectionResolver {
            public function __construct(private $test) {}

            public function resolve(): ?array
            {
                return [
                    'driver'   => 'sqlite',
                    'database' => $this->test->secondaryDbPath,
                    'prefix'   => '',
                ];
            }
        };
    });

    $names = DatabaseTable::getAllModels()->pluck('name');

    expect(config('dbmyadmin.connection'))->toBe('dbmyadmin_active');
    expect($names)->toContain('secondary_only_table');
    expect($names)->not->toContain('test_users');
});

it('falls back to the app default connection when the resolver returns null', function () {
    $names = DatabaseTable::getAllModels()->pluck('name');

    expect(config('dbmyadmin.connection'))->toBeNull();
    expect($names)->not->toContain('secondary_only_table');
});

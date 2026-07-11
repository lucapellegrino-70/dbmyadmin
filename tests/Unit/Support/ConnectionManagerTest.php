<?php

use Illuminate\Support\Facades\DB;
use LucaPellegrino\DbMyAdmin\Contracts\ActiveConnectionResolver;
use LucaPellegrino\DbMyAdmin\Support\ConnectionManager;

afterEach(function () {
    ConnectionManager::reset();
});

it('returns the default connection when no resolver override is bound', function () {
    $connection = ConnectionManager::connection();

    expect($connection->getName())->toBe(config('database.default'));
});

it('activates a named connection returned by the bound resolver, only once per request', function () {
    app()->bind(ActiveConnectionResolver::class, function () {
        return new class implements ActiveConnectionResolver {
            public int $calls = 0;

            public function resolve(): ?array
            {
                $this->calls++;

                return [
                    'driver'   => 'sqlite',
                    'database' => ':memory:',
                    'prefix'   => '',
                ];
            }
        };
    });

    ConnectionManager::connection();
    ConnectionManager::connection();

    expect(config('dbmyadmin.connection'))->toBe('dbmyadmin_active');
    expect(config('database.connections.dbmyadmin_active.driver'))->toBe('sqlite');
});

it('reset() allows re-activation on the next call', function () {
    ConnectionManager::connection();
    ConnectionManager::reset();

    app()->bind(ActiveConnectionResolver::class, function () {
        return new class implements ActiveConnectionResolver {
            public function resolve(): ?array
            {
                return ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''];
            }
        };
    });

    ConnectionManager::connection();

    expect(config('dbmyadmin.connection'))->toBe('dbmyadmin_active');
});

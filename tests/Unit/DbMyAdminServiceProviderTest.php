<?php

use LucaPellegrino\DbMyAdmin\Contracts\ActiveConnectionResolver;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
use LucaPellegrino\DbMyAdmin\Drivers\SqliteDriver;
use LucaPellegrino\DbMyAdmin\Support\ConnectionManager;
use LucaPellegrino\DbMyAdmin\Support\NullConnectionResolver;

afterEach(function () {
    ConnectionManager::reset();
});

it('binds NullConnectionResolver as the default ActiveConnectionResolver', function () {
    expect(app(ActiveConnectionResolver::class))->toBeInstanceOf(NullConnectionResolver::class);
});

it('resolves DatabaseDriver from the config-driven driver map', function () {
    expect(app(DatabaseDriver::class))->toBeInstanceOf(SqliteDriver::class);
});

it('throws a clear error for an unmapped driver', function () {
    config(['dbmyadmin.driver' => 'oracle']);

    expect(fn () => app()->forgetInstance(DatabaseDriver::class) ?? app(DatabaseDriver::class))
        ->toThrow(RuntimeException::class, 'unsupported database driver [oracle]');
});

it('binds NullConnectionLabelProvider as the default ActiveConnectionLabelProvider', function () {
    expect(app(\LucaPellegrino\DbMyAdmin\Contracts\ActiveConnectionLabelProvider::class))
        ->toBeInstanceOf(\LucaPellegrino\DbMyAdmin\Support\NullConnectionLabelProvider::class);

    expect(app(\LucaPellegrino\DbMyAdmin\Contracts\ActiveConnectionLabelProvider::class)->label())->toBeNull();
});

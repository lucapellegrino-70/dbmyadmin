<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
use LucaPellegrino\DbMyAdmin\Drivers\SqliteDriver;

beforeEach(function () {
    Schema::create('test_users', function ($table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('test_users');
    Schema::dropIfExists('articles');
});

it('implements DatabaseDriver', function () {
    expect(SqliteDriver::class)->toImplement(DatabaseDriver::class);
});

it('getTables returns the test table', function () {
    $driver = new SqliteDriver();
    $tables = $driver->getTables();

    expect($tables)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($tables->pluck('name'))->toContain('test_users');
});

it('getColumns returns columns for test table', function () {
    $driver  = new SqliteDriver();
    $columns = $driver->getColumns('test_users');
    $names   = $columns->pluck('name')->toArray();

    expect($names)->toContain('id', 'name', 'email');
});

it('supportsFeature returns true for browse_records', function () {
    expect((new SqliteDriver())->supportsFeature('browse_records'))->toBeTrue();
});

it('supportsFeature returns false for table_stats', function () {
    expect((new SqliteDriver())->supportsFeature('table_stats'))->toBeFalse();
});

it('buildCreateDdl generates executable SQL', function () {
    $driver  = new SqliteDriver();
    $columns = [
        ['name' => 'id',    'type' => 'INTEGER', 'primary' => true,  'auto_increment' => true,  'nullable' => false, 'default' => null, 'length' => null,  'unsigned' => false],
        ['name' => 'title', 'type' => 'VARCHAR', 'primary' => false, 'auto_increment' => false, 'nullable' => false, 'default' => null, 'length' => 255,   'unsigned' => false],
    ];
    $ddl = $driver->buildCreateDdl('articles', $columns, []);

    expect($ddl)->toContain('CREATE TABLE')
                ->toContain('articles')
                ->toContain('title');

    DB::unprepared($ddl);
    expect(Schema::hasTable('articles'))->toBeTrue();
});

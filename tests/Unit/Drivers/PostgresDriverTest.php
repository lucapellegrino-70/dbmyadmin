<?php

use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
use LucaPellegrino\DbMyAdmin\Drivers\PostgresDriver;

it('implements DatabaseDriver', function () {
    expect(PostgresDriver::class)->toImplement(DatabaseDriver::class);
});

it('supportsFeature returns true for all standard features', function (string $feature) {
    expect((new PostgresDriver())->supportsFeature($feature))->toBeTrue();
})->with(['browse_records', 'create_table', 'alter_table', 'foreign_keys', 'drop_column', 'table_stats']);

it('buildCreateDdl uses double-quoted identifiers', function () {
    $driver  = new PostgresDriver();
    $columns = [
        ['name' => 'id',    'type' => 'BIGINT', 'primary' => true,  'auto_increment' => true,  'nullable' => false, 'default' => null, 'length' => null,  'unsigned' => false, 'composite_pk' => false],
        ['name' => 'title', 'type' => 'VARCHAR','primary' => false, 'auto_increment' => false, 'nullable' => false, 'default' => null, 'length' => 255,   'unsigned' => false, 'composite_pk' => false],
    ];
    $ddl = $driver->buildCreateDdl('articles', $columns, []);

    expect($ddl)->toContain('CREATE TABLE')
                ->toContain('"articles"')
                ->toContain('"id"')
                ->toContain('"title"');
});

it('buildCreateDdl maps BIGINT + auto_increment to BIGSERIAL', function () {
    $driver  = new PostgresDriver();
    $columns = [
        ['name' => 'id', 'type' => 'BIGINT', 'primary' => true, 'auto_increment' => true, 'nullable' => false, 'default' => null, 'length' => null, 'unsigned' => false, 'composite_pk' => false],
    ];
    $ddl = $driver->buildCreateDdl('orders', $columns, []);

    expect($ddl)->toContain('BIGSERIAL');
});

it('buildCreateDdl maps INT + auto_increment to SERIAL', function () {
    $driver  = new PostgresDriver();
    $columns = [
        ['name' => 'id', 'type' => 'INT', 'primary' => true, 'auto_increment' => true, 'nullable' => false, 'default' => null, 'length' => null, 'unsigned' => false, 'composite_pk' => false],
    ];
    $ddl = $driver->buildCreateDdl('items', $columns, []);

    expect($ddl)->toContain('SERIAL');
});

it('buildCreateDdl maps DATETIME to TIMESTAMP', function () {
    $driver  = new PostgresDriver();
    $columns = [
        ['name' => 'created_at', 'type' => 'DATETIME', 'primary' => false, 'auto_increment' => false, 'nullable' => true, 'default' => null, 'length' => null, 'unsigned' => false, 'composite_pk' => false],
    ];
    $ddl = $driver->buildCreateDdl('events', $columns, []);

    expect($ddl)->toContain('TIMESTAMP');
});

it('buildAlterDdl uses ALTER COLUMN TYPE for modifications', function () {
    $driver  = new PostgresDriver();
    $changes = [
        'modify' => [
            ['name' => 'title', 'type' => 'VARCHAR', 'length' => 500, 'nullable' => true, 'default' => null],
        ],
        'add'  => [],
        'drop' => [],
    ];
    $ddl = $driver->buildAlterDdl('articles', $changes);

    expect($ddl)->toContain('ALTER TABLE')
                ->toContain('"articles"')
                ->toContain('ALTER COLUMN')
                ->toContain('"title"')
                ->toContain('TYPE VARCHAR');
});

it('buildAlterDdl generates DROP COLUMN for drops', function () {
    $driver  = new PostgresDriver();
    $changes = ['modify' => [], 'add' => [], 'drop' => ['obsolete_col']];
    $ddl     = $driver->buildAlterDdl('articles', $changes);

    expect($ddl)->toContain('DROP COLUMN')
                ->toContain('"obsolete_col"');
});

it('buildAlterDdl returns empty string when no changes', function () {
    $driver  = new PostgresDriver();
    $changes = ['modify' => [], 'add' => [], 'drop' => []];

    expect($driver->buildAlterDdl('articles', $changes))->toBe('');
});

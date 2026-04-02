<?php

use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
use LucaPellegrino\DbMyAdmin\Drivers\MySqlDriver;

it('implements DatabaseDriver', function () {
    expect(MySqlDriver::class)->toImplement(DatabaseDriver::class);
});

it('supportsFeature returns true for table_stats', function () {
    expect((new MySqlDriver())->supportsFeature('table_stats'))->toBeTrue();
});

it('supportsFeature returns true for drop_column', function () {
    expect((new MySqlDriver())->supportsFeature('drop_column'))->toBeTrue();
});

it('buildCreateDdl generates ENGINE clause', function () {
    $driver  = new MySqlDriver();
    $columns = [
        ['name' => 'id',    'type' => 'BIGINT', 'primary' => true,  'auto_increment' => true,  'nullable' => false, 'default' => null, 'length' => null, 'unsigned' => true,  'composite_pk' => false],
        ['name' => 'title', 'type' => 'VARCHAR','primary' => false, 'auto_increment' => false, 'nullable' => false, 'default' => null, 'length' => 255,  'unsigned' => false, 'composite_pk' => false],
    ];
    $ddl = $driver->buildCreateDdl('articles', $columns, [], ['engine' => 'InnoDB', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci']);

    expect($ddl)->toContain('CREATE TABLE')
                ->toContain('`articles`')
                ->toContain('ENGINE=InnoDB')
                ->toContain('CHARSET=utf8mb4');
});

it('buildAlterDdl generates MODIFY COLUMN', function () {
    $driver  = new MySqlDriver();
    $changes = [
        'modify' => [
            ['name' => 'title', 'type' => 'VARCHAR', 'length' => 500, 'nullable' => true, 'default' => null, 'unsigned' => false, 'auto_increment' => false, 'composite_pk' => false],
        ],
        'add'  => [],
        'drop' => [],
    ];
    $ddl = $driver->buildAlterDdl('articles', $changes);

    expect($ddl)->toContain('ALTER TABLE')
                ->toContain('MODIFY COLUMN')
                ->toContain('`title`');
});

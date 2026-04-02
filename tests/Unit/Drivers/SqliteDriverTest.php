<?php

use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
use LucaPellegrino\DbMyAdmin\Drivers\SqliteDriver;

it('SqliteDriver implements DatabaseDriver contract', function () {
    expect(SqliteDriver::class)->toImplement(DatabaseDriver::class);
});

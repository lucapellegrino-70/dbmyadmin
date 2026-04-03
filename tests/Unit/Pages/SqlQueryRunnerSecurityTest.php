<?php

use LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource\Pages\SqlQueryRunner;

// ── isBlockedStatement ────────────────────────────────────────────────────────

it('blocks DROP statements', function () {
    expect((new SqlQueryRunner())->isBlockedStatement('DROP TABLE users'))->toBe('DROP');
});

it('blocks TRUNCATE statements', function () {
    expect((new SqlQueryRunner())->isBlockedStatement('TRUNCATE TABLE users'))->toBe('TRUNCATE');
});

it('blocks ALTER statements', function () {
    expect((new SqlQueryRunner())->isBlockedStatement('ALTER TABLE users ADD COLUMN x INT'))->toBe('ALTER');
});

it('blocks CREATE statements', function () {
    expect((new SqlQueryRunner())->isBlockedStatement('CREATE TABLE new_table (id INT)'))->toBe('CREATE');
});

it('blocks GRANT statements', function () {
    expect((new SqlQueryRunner())->isBlockedStatement('GRANT ALL ON users TO admin'))->toBe('GRANT');
});

it('does not block SELECT statements', function () {
    expect((new SqlQueryRunner())->isBlockedStatement('SELECT * FROM users'))->toBeNull();
});

it('does not block INSERT statements', function () {
    expect((new SqlQueryRunner())->isBlockedStatement('INSERT INTO users (name) VALUES (\'test\')'))->toBeNull();
});

it('does not block UPDATE statements', function () {
    expect((new SqlQueryRunner())->isBlockedStatement('UPDATE users SET name = \'test\' WHERE id = 1'))->toBeNull();
});

it('does not block DELETE statements', function () {
    expect((new SqlQueryRunner())->isBlockedStatement('DELETE FROM users WHERE id = 1'))->toBeNull();
});

it('is case-insensitive for blocked statements', function () {
    expect((new SqlQueryRunner())->isBlockedStatement('drop table users'))->toBe('DROP');
    expect((new SqlQueryRunner())->isBlockedStatement('  DROP TABLE users'))->toBe('DROP');
});

// ── Config-driven blocked statements ─────────────────────────────────────────

it('respects custom blocked_statements config', function () {
    config(['dbmyadmin.query_runner.blocked_statements' => ['SELECT']]);

    expect((new SqlQueryRunner())->isBlockedStatement('SELECT * FROM users'))->toBe('SELECT');
    expect((new SqlQueryRunner())->isBlockedStatement('INSERT INTO t VALUES (1)'))->toBeNull();
});

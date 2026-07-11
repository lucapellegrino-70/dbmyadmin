<?php

namespace LucaPellegrino\DbMyAdmin\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\DB;
use LucaPellegrino\DbMyAdmin\Contracts\ActiveConnectionResolver;

/**
 * Single choke point every driver/model/page goes through to reach the
 * database, instead of the raw DB/Schema facades. This is what lets an
 * extension package point DbMyAdmin at a different connection per request
 * without ever touching database.default (see docs/superpowers/specs/
 * 2026-07-11-connection-manager-pro-design.md, section 2.2/2.3).
 */
class ConnectionManager
{
    protected static bool $activated = false;

    public static function connection(): Connection
    {
        static::activate();

        return DB::connection(config('dbmyadmin.connection'));
    }

    public static function schema(): Builder
    {
        return static::connection()->getSchemaBuilder();
    }

    public static function activate(): void
    {
        if (static::$activated) {
            return;
        }

        $config = app(ActiveConnectionResolver::class)->resolve();

        static::$activated = true;

        if ($config === null) {
            return;
        }

        config(['database.connections.dbmyadmin_active' => $config]);
        DB::purge('dbmyadmin_active');
        config(['dbmyadmin.connection' => 'dbmyadmin_active']);
    }

    /**
     * Test-only: static state persists across Pest test functions within the
     * same PHP process even though Testbench rebuilds $this->app per test.
     * Call this in beforeEach()/afterEach() the same way tests already call
     * DatabaseTable::clearCache().
     */
    public static function reset(): void
    {
        static::$activated = false;
        config(['dbmyadmin.connection' => null]);
    }
}

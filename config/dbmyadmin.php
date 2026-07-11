<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Database Driver
    |--------------------------------------------------------------------------
    | 'auto' detects the driver from the active DB connection.
    | Accepted values: 'auto', or any key present in 'drivers' below.
    */
    'driver' => 'auto',

    /*
    |--------------------------------------------------------------------------
    | Driver Class Map
    |--------------------------------------------------------------------------
    | Maps a driver name (as returned by Connection::getDriverName(), or set
    | explicitly above) to the DatabaseDriver implementation that handles it.
    | Extension packages (e.g. a commercial add-on) can merge additional
    | entries into this array from their own service provider without
    | forking this package.
    */
    'drivers' => [
        'mysql'   => \LucaPellegrino\DbMyAdmin\Drivers\MySqlDriver::class,
        'mariadb' => \LucaPellegrino\DbMyAdmin\Drivers\MySqlDriver::class,
        'pgsql'   => \LucaPellegrino\DbMyAdmin\Drivers\PostgresDriver::class,
        'sqlite'  => \LucaPellegrino\DbMyAdmin\Drivers\SqliteDriver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Active Named Connection
    |--------------------------------------------------------------------------
    | Null means "use the host application's default database connection"
    | (the only behavior this package has without an extension installed).
    | Do not set this by hand — it's managed at runtime by
    | LucaPellegrino\DbMyAdmin\Support\ConnectionManager.
    */
    'connection' => null,

    /*
    |--------------------------------------------------------------------------
    | Excluded Tables
    |--------------------------------------------------------------------------
    */
    'excluded_tables' => [
        'migrations',
        'dbmyadmin_saved_queries',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'password_reset_tokens',
        'personal_access_tokens',
    ],

    /*
    |--------------------------------------------------------------------------
    | SQL Query Runner
    |--------------------------------------------------------------------------
    */
    'query_runner' => [
        'blocked_statements' => [
            'DROP', 'TRUNCATE', 'ALTER', 'CREATE',
            'RENAME', 'GRANT', 'REVOKE', 'LOCK', 'UNLOCK',
        ],
        'max_rows' => 1000,
    ],

    'saved_queries_table' => 'dbmyadmin_saved_queries',

    'logging' => true,
];

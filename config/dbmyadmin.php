<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Database Driver
    |--------------------------------------------------------------------------
    | 'auto' detects the driver from the active DB connection.
    | Accepted values: 'auto', 'mysql', 'pgsql', 'sqlite'
    */
    'driver' => 'auto',

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

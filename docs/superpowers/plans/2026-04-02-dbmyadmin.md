# DbMyAdmin — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build `lucapellegrino/dbmyadmin` — a phpMyAdmin-like Filament 5 plugin supporting MySQL, PostgreSQL, and SQLite, distributed as a public Composer package.

**Architecture:** Single Composer package with a driver layer (MySqlDriver, PostgresDriver, SqliteDriver) abstracting database-specific introspection and DDL. A Filament 5 Plugin class registers one Resource with 5 custom Livewire 4 pages. Existing Filament 3.3 source files (currently flat in project root) are migrated to the new package namespace and updated for Filament 5 + Livewire 4 APIs. The original code used `eval()` to create dynamic Eloquent models — this plan replaces that pattern with PHP anonymous classes for safety.

**Tech Stack:** PHP 8.3, Laravel 12, Filament 5, Livewire 4, Pest 3, Orchestra Testbench 10

> **Filament 5 + Livewire 4 API note:** Verify current method signatures against official docs before implementing each task:
> - https://filamentphp.com/docs
> - https://livewire.laravel.com/docs/upgrading

---

## File Map

| File | Status | Responsibility |
|------|--------|---------------|
| `composer.json` | New | Package metadata, dependencies, PSR-4 autoload |
| `src/DbMyAdminServiceProvider.php` | New | Register config, views, migrations, driver binding |
| `src/DbMyAdminPlugin.php` | New | Filament 5 plugin class, fluent config API |
| `src/Contracts/DatabaseDriver.php` | New | Driver interface |
| `src/Drivers/MySqlDriver.php` | New | MySQL/MariaDB introspection + DDL |
| `src/Drivers/PostgresDriver.php` | New | PostgreSQL introspection + DDL |
| `src/Drivers/SqliteDriver.php` | New | SQLite introspection + DDL |
| `src/Models/SavedQuery.php` | Migrate | Config-driven table name |
| `src/Models/DatabaseTable.php` | Migrate | Namespace, driver injection |
| `src/Models/DynamicTableModel.php` | New | Safe anonymous Eloquent model for BrowseTableRecords |
| `src/Resources/DatabaseTableResource.php` | Migrate | Namespace, Filament 5 API, authorization |
| `src/Resources/DatabaseTableResource/Pages/ListDatabaseTables.php` | Migrate | Namespace, Filament 5 API |
| `src/Resources/DatabaseTableResource/Pages/BrowseTableRecords.php` | Migrate | Namespace, DynamicTableModel, Livewire 4 |
| `src/Resources/DatabaseTableResource/Pages/CreateTable.php` | Migrate | Namespace, driver injection, Livewire 4 |
| `src/Resources/DatabaseTableResource/Pages/AlterTable.php` | Migrate | Namespace, driver injection, Livewire 4 |
| `src/Resources/DatabaseTableResource/Pages/SqlQueryRunner.php` | Migrate | Namespace, config-driven blocked statements, Livewire 4 |
| `config/dbmyadmin.php` | New | All plugin configuration |
| `database/migrations/create_dbmyadmin_saved_queries_table.php` | Migrate | Rename table to `dbmyadmin_saved_queries` |
| `resources/views/pages/list-database-tables.blade.php` | Migrate | View namespace, theme colors |
| `resources/views/pages/browse-table-records.blade.php` | Migrate | View namespace, theme colors |
| `resources/views/pages/create-table.blade.php` | Migrate | View namespace, theme colors, Livewire 4 |
| `resources/views/pages/alter-table.blade.php` | Migrate | View namespace, theme colors, Livewire 4 |
| `resources/views/pages/sql-query-runner.blade.php` | Migrate | View namespace, theme colors, Livewire 4 |
| `resources/views/table-structure.blade.php` | Migrate | View namespace, theme colors |
| `tests/TestCase.php` | New | Base test case with package bootstrapping |
| `tests/Pest.php` | New | Pest configuration |
| `tests/Unit/Drivers/SqliteDriverTest.php` | New | SQLite driver unit tests |
| `tests/Unit/Drivers/MySqlDriverTest.php` | New | MySQL driver unit tests |
| `tests/Unit/Models/SavedQueryTest.php` | New | SavedQuery model tests |
| `tests/Unit/Models/DynamicTableModelTest.php` | New | DynamicTableModel tests |
| `tests/Feature/SqlQueryRunnerBlockedStatementsTest.php` | New | SQL Runner security tests |

---

## Task 1: Package scaffolding

**Files:**
- Create: `composer.json`
- Create: `.gitignore`
- Create: `phpunit.xml`

- [ ] **Step 1: Create the directory structure**

```bash
cd D:/development/lukemyadmin
mkdir -p src/Contracts src/Drivers src/Models src/Resources/DatabaseTableResource/Pages
mkdir -p resources/views/pages
mkdir -p database/migrations
mkdir -p config
mkdir -p tests/Unit/Drivers tests/Unit/Models tests/Feature
mkdir -p docs/superpowers/plans
```

- [ ] **Step 2: Create `composer.json`**

```json
{
    "name": "lucapellegrino/dbmyadmin",
    "description": "A phpMyAdmin-like database management interface for Filament 5",
    "type": "library",
    "license": "MIT",
    "keywords": ["filament", "laravel", "database", "phpmyadmin", "admin"],
    "authors": [
        {
            "name": "Luca Pellegrino"
        }
    ],
    "require": {
        "php": "^8.3",
        "filament/filament": "^5.0",
        "laravel/framework": "^12.0"
    },
    "require-dev": {
        "orchestra/testbench": "^10.0",
        "pestphp/pest": "^3.0",
        "pestphp/pest-plugin-laravel": "^3.0"
    },
    "autoload": {
        "psr-4": {
            "LucaPellegrino\\DbMyAdmin\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "LucaPellegrino\\DbMyAdmin\\Tests\\": "tests/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "LucaPellegrino\\DbMyAdmin\\DbMyAdminServiceProvider"
            ]
        }
    },
    "config": {
        "sort-packages": true,
        "allow-plugins": {
            "pestphp/pest-plugin": true
        }
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

- [ ] **Step 3: Create `.gitignore`**

```
/vendor/
/node_modules/
.env
.phpunit.result.cache
composer.lock
```

- [ ] **Step 4: Create `phpunit.xml`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
    <php>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
        <env name="APP_KEY" value="base64:2fl+Ktvkfl+Ktvkfl+Ktvkfl+Ktvkfl+Ktvkfl+Ktv="/>
    </php>
</phpunit>
```

- [ ] **Step 5: Install dependencies**

```bash
composer install
```

Expected: Vendors installed in `/vendor`, no errors.

- [ ] **Step 6: Initialize git and commit**

```bash
git init
git add composer.json .gitignore phpunit.xml
git commit -m "feat: initialize package scaffold"
```

---

## Task 2: Service Provider + Plugin class

**Files:**
- Create: `src/DbMyAdminServiceProvider.php`
- Create: `src/DbMyAdminPlugin.php`

- [ ] **Step 1: Create `src/DbMyAdminServiceProvider.php`**

```php
<?php

namespace LucaPellegrino\DbMyAdmin;

use Illuminate\Support\ServiceProvider;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
use LucaPellegrino\DbMyAdmin\Drivers\MySqlDriver;
use LucaPellegrino\DbMyAdmin\Drivers\PostgresDriver;
use LucaPellegrino\DbMyAdmin\Drivers\SqliteDriver;

class DbMyAdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/dbmyadmin.php', 'dbmyadmin');

        $this->app->singleton(DatabaseDriver::class, function ($app) {
            $configured = config('dbmyadmin.driver', 'auto');
            $driver = $configured === 'auto'
                ? $app['db']->connection()->getDriverName()
                : $configured;

            return match ($driver) {
                'mysql', 'mariadb' => new MySqlDriver(),
                'pgsql'            => new PostgresDriver(),
                'sqlite'           => new SqliteDriver(),
                default            => throw new \RuntimeException(
                    "DbMyAdmin: unsupported database driver [{$driver}]. Supported: mysql, pgsql, sqlite."
                ),
            };
        });
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'dbmyadmin');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/dbmyadmin.php' => config_path('dbmyadmin.php'),
            ], 'dbmyadmin-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'dbmyadmin-migrations');
        }
    }
}
```

- [ ] **Step 2: Create `src/DbMyAdminPlugin.php`**

```php
<?php

namespace LucaPellegrino\DbMyAdmin;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource;

class DbMyAdminPlugin implements Plugin
{
    protected ?string  $navigationGroup       = null;
    protected ?string  $navigationIcon        = null;
    protected ?Closure $authorizationCallback = null;

    public static function make(): static
    {
        return new static();
    }

    public function getId(): string
    {
        return 'dbmyadmin';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            DatabaseTableResource::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public function navigationGroup(string $group): static
    {
        $this->navigationGroup = $group;
        return $this;
    }

    public function navigationIcon(string $icon): static
    {
        $this->navigationIcon = $icon;
        return $this;
    }

    public function authorize(Closure $callback): static
    {
        $this->authorizationCallback = $callback;
        return $this;
    }

    public function getNavigationGroup(): ?string
    {
        return $this->navigationGroup;
    }

    public function getNavigationIcon(): ?string
    {
        return $this->navigationIcon;
    }

    public function isAuthorized(): bool
    {
        if ($this->authorizationCallback === null) {
            return true;
        }

        return (bool) call_user_func($this->authorizationCallback);
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add src/DbMyAdminServiceProvider.php src/DbMyAdminPlugin.php
git commit -m "feat: add service provider and plugin class"
```

---

## Task 3: Config + migration + test infrastructure

**Files:**
- Create: `config/dbmyadmin.php`
- Create: `database/migrations/create_dbmyadmin_saved_queries_table.php`
- Create: `tests/TestCase.php`
- Create: `tests/Pest.php`

- [ ] **Step 1: Create `config/dbmyadmin.php`**

```php
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
```

- [ ] **Step 2: Create `database/migrations/create_dbmyadmin_saved_queries_table.php`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('dbmyadmin.saved_queries_table', 'dbmyadmin_saved_queries'), function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->longText('sql');
            $table->string('created_by')->nullable();
            $table->timestamps();
            $table->index('created_by');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('dbmyadmin.saved_queries_table', 'dbmyadmin_saved_queries'));
    }
};
```

- [ ] **Step 3: Create `tests/TestCase.php`**

```php
<?php

namespace LucaPellegrino\DbMyAdmin\Tests;

use LucaPellegrino\DbMyAdmin\DbMyAdminServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            DbMyAdminServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
```

- [ ] **Step 4: Create `tests/Pest.php`**

```php
<?php

uses(LucaPellegrino\DbMyAdmin\Tests\TestCase::class)->in('Unit', 'Feature');
```

- [ ] **Step 5: Run tests to verify infrastructure**

```bash
./vendor/bin/pest --no-coverage
```

Expected: "No tests found" with exit code 0. If errors occur, check that `orchestra/testbench ^10.0` supports Laravel 12.

- [ ] **Step 6: Commit**

```bash
git add config/ database/ tests/
git commit -m "feat: add config, migration, and test infrastructure"
```

---

## Task 4: DatabaseDriver contract

**Files:**
- Create: `src/Contracts/DatabaseDriver.php`
- Create: `tests/Unit/Drivers/SqliteDriverTest.php` (stub for contract check)

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Drivers/SqliteDriverTest.php`:

```php
<?php

use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
use LucaPellegrino\DbMyAdmin\Drivers\SqliteDriver;

it('SqliteDriver implements DatabaseDriver contract', function () {
    expect(SqliteDriver::class)->toImplement(DatabaseDriver::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
./vendor/bin/pest tests/Unit/Drivers/SqliteDriverTest.php -v
```

Expected: FAIL — `SqliteDriver` class not found.

- [ ] **Step 3: Create `src/Contracts/DatabaseDriver.php`**

```php
<?php

namespace LucaPellegrino\DbMyAdmin\Contracts;

use Illuminate\Support\Collection;

interface DatabaseDriver
{
    /**
     * Return all tables in the database.
     * Each item: ['name', 'rows', 'data_length', 'index_length', 'engine', 'collation']
     * Stats fields may be null for drivers that don't support them (e.g. SQLite).
     */
    public function getTables(): Collection;

    /**
     * Return column definitions for a table.
     * Each item: ['name', 'type', 'nullable', 'default', 'key', 'extra', 'length']
     */
    public function getColumns(string $table): Collection;

    /**
     * Return foreign key constraints for a table.
     * Each item: ['column', 'referenced_table', 'referenced_column', 'on_delete', 'on_update']
     */
    public function getForeignKeys(string $table): Collection;

    /**
     * Return indexes for a table.
     * Each item: ['name', 'columns', 'unique', 'primary']
     */
    public function getIndexes(string $table): Collection;

    /**
     * Build an ALTER TABLE DDL string.
     * $changes: ['modify' => [...columns], 'add' => [...columns], 'drop' => [...column names]]
     */
    public function buildAlterDdl(string $table, array $changes): string;

    /**
     * Build a CREATE TABLE DDL string.
     * $columns: array of column definitions
     * $fks: array of foreign key definitions
     * $options: driver-specific options (engine, charset, collation, comment)
     */
    public function buildCreateDdl(string $table, array $columns, array $fks, array $options = []): string;

    /**
     * Check if this driver supports a given feature.
     * Known features: 'alter_table', 'drop_column', 'foreign_keys', 'table_stats'
     */
    public function supportsFeature(string $feature): bool;
}
```

- [ ] **Step 4: Commit**

```bash
git add src/Contracts/DatabaseDriver.php tests/Unit/Drivers/SqliteDriverTest.php
git commit -m "feat: add DatabaseDriver contract"
```

---

## Task 5: SqliteDriver

**Files:**
- Create: `src/Drivers/SqliteDriver.php`
- Expand: `tests/Unit/Drivers/SqliteDriverTest.php`

- [ ] **Step 1: Expand `tests/Unit/Drivers/SqliteDriverTest.php`**

```php
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
```

- [ ] **Step 2: Run tests to verify failure**

```bash
./vendor/bin/pest tests/Unit/Drivers/SqliteDriverTest.php -v
```

Expected: FAIL — `SqliteDriver` class not found.

- [ ] **Step 3: Create `src/Drivers/SqliteDriver.php`**

```php
<?php

namespace LucaPellegrino\DbMyAdmin\Drivers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;

class SqliteDriver implements DatabaseDriver
{
    public function getTables(): Collection
    {
        $tables = DB::select(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
        );

        return collect($tables)->map(fn ($t) => [
            'name'         => $t->name,
            'rows'         => null,
            'data_length'  => null,
            'index_length' => null,
            'engine'       => null,
            'collation'    => null,
        ]);
    }

    public function getColumns(string $table): Collection
    {
        $quoted = DB::connection()->getPdo()->quote($table);
        $rows   = DB::select("PRAGMA table_info({$quoted})");

        return collect($rows)->map(fn ($row) => [
            'name'     => $row->name,
            'type'     => strtoupper($row->type ?: 'TEXT'),
            'nullable' => ! $row->notnull,
            'default'  => $row->dflt_value,
            'key'      => $row->pk ? 'PRI' : '',
            'extra'    => $row->pk ? 'auto_increment' : '',
            'length'   => null,
        ]);
    }

    public function getForeignKeys(string $table): Collection
    {
        $quoted = DB::connection()->getPdo()->quote($table);
        $rows   = DB::select("PRAGMA foreign_key_list({$quoted})");

        return collect($rows)->map(fn ($row) => [
            'column'            => $row->from,
            'referenced_table'  => $row->table,
            'referenced_column' => $row->to,
            'on_delete'         => $row->on_delete,
            'on_update'         => $row->on_update,
        ]);
    }

    public function getIndexes(string $table): Collection
    {
        $quoted  = DB::connection()->getPdo()->quote($table);
        $indexes = DB::select("PRAGMA index_list({$quoted})");

        return collect($indexes)->map(function ($idx) {
            $info = DB::select("PRAGMA index_info({$idx->name})");
            return [
                'name'    => $idx->name,
                'columns' => collect($info)->pluck('name')->toArray(),
                'unique'  => (bool) $idx->unique,
                'primary' => $idx->origin === 'pk',
            ];
        });
    }

    public function buildAlterDdl(string $table, array $changes): string
    {
        $statements = [];

        foreach ($changes['add'] ?? [] as $col) {
            $statements[] = "ALTER TABLE \"{$table}\" ADD COLUMN " . $this->buildColumnDef($col) . ";";
        }

        foreach ($changes['drop'] ?? [] as $colName) {
            $statements[] = "ALTER TABLE \"{$table}\" DROP COLUMN \"{$colName}\";";
        }

        if (! empty($changes['modify'] ?? [])) {
            $statements[] = "-- SQLite does not support MODIFY COLUMN. Recreate the table to change column definitions.";
        }

        return implode("\n", $statements);
    }

    public function buildCreateDdl(string $table, array $columns, array $fks, array $options = []): string
    {
        $lines = [];

        foreach ($columns as $col) {
            $lines[] = '  ' . $this->buildColumnDef($col);
        }

        foreach ($fks as $fk) {
            $lines[] = sprintf(
                '  FOREIGN KEY ("%s") REFERENCES "%s" ("%s") ON DELETE %s ON UPDATE %s',
                $fk['column'],
                $fk['referenced_table'],
                $fk['referenced_column'],
                $fk['on_delete'] ?? 'RESTRICT',
                $fk['on_update'] ?? 'RESTRICT',
            );
        }

        return sprintf("CREATE TABLE \"%s\" (\n%s\n);", $table, implode(",\n", $lines));
    }

    public function supportsFeature(string $feature): bool
    {
        return match ($feature) {
            'browse_records', 'create_table', 'alter_table', 'foreign_keys' => true,
            'drop_column'  => $this->dropColumnSupported(),
            'table_stats'  => false,
            default        => false,
        };
    }

    private function buildColumnDef(array $col): string
    {
        $type = strtoupper($col['type']);
        $def  = "\"{$col['name']}\" {$type}";

        if (! empty($col['length'])) {
            $def .= "({$col['length']})";
        }

        if (! empty($col['primary']) && ! empty($col['auto_increment'])) {
            $def .= ' PRIMARY KEY AUTOINCREMENT';
        } elseif (! empty($col['primary'])) {
            $def .= ' PRIMARY KEY';
        }

        if (empty($col['nullable'])) {
            $def .= ' NOT NULL';
        }

        if ($col['default'] !== null && $col['default'] !== '') {
            $def .= ' DEFAULT ' . $col['default'];
        }

        return $def;
    }

    private function dropColumnSupported(): bool
    {
        $version = DB::select('SELECT sqlite_version() AS v')[0]->v ?? '0';
        return version_compare($version, '3.35.0', '>=');
    }
}
```

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/pest tests/Unit/Drivers/SqliteDriverTest.php -v
```

Expected: All PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Drivers/SqliteDriver.php tests/Unit/Drivers/SqliteDriverTest.php
git commit -m "feat: add SqliteDriver with unit tests"
```

---

## Task 6: MySqlDriver

**Files:**
- Create: `src/Drivers/MySqlDriver.php`
- Create: `tests/Unit/Drivers/MySqlDriverTest.php`

> MySQL tests that require a live DB connection can be skipped in CI. DDL builder tests run without a connection.

- [ ] **Step 1: Create `tests/Unit/Drivers/MySqlDriverTest.php`**

```php
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
```

- [ ] **Step 2: Run to verify failure**

```bash
./vendor/bin/pest tests/Unit/Drivers/MySqlDriverTest.php -v
```

Expected: FAIL — `MySqlDriver` class not found.

- [ ] **Step 3: Create `src/Drivers/MySqlDriver.php`**

```php
<?php

namespace LucaPellegrino\DbMyAdmin\Drivers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;

class MySqlDriver implements DatabaseDriver
{
    public function getTables(): Collection
    {
        $dbName = DB::connection()->getDatabaseName();
        $rows   = DB::select("
            SELECT
                TABLE_NAME      AS `name`,
                TABLE_ROWS      AS `rows`,
                DATA_LENGTH     AS `data_length`,
                INDEX_LENGTH    AS `index_length`,
                ENGINE          AS `engine`,
                TABLE_COLLATION AS `collation`
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = ?
            ORDER BY TABLE_NAME
        ", [$dbName]);

        return collect($rows)->map(fn ($r) => (array) $r);
    }

    public function getColumns(string $table): Collection
    {
        $dbName = DB::connection()->getDatabaseName();
        $rows   = DB::select("
            SELECT
                COLUMN_NAME              AS `name`,
                DATA_TYPE                AS `type`,
                IS_NULLABLE              AS `nullable`,
                COLUMN_DEFAULT           AS `default`,
                COLUMN_KEY               AS `key`,
                EXTRA                    AS `extra`,
                CHARACTER_MAXIMUM_LENGTH AS `length`
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
            ORDER BY ORDINAL_POSITION
        ", [$dbName, $table]);

        return collect($rows)->map(function ($r) {
            $row             = (array) $r;
            $row['type']     = strtoupper($row['type']);
            $row['nullable'] = $row['nullable'] === 'YES';
            return $row;
        });
    }

    public function getForeignKeys(string $table): Collection
    {
        $dbName = DB::connection()->getDatabaseName();
        $rows   = DB::select("
            SELECT
                kcu.COLUMN_NAME            AS `column`,
                kcu.REFERENCED_TABLE_NAME  AS `referenced_table`,
                kcu.REFERENCED_COLUMN_NAME AS `referenced_column`,
                rc.DELETE_RULE             AS `on_delete`,
                rc.UPDATE_RULE             AS `on_update`
            FROM information_schema.KEY_COLUMN_USAGE kcu
            JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
                ON rc.CONSTRAINT_NAME   = kcu.CONSTRAINT_NAME
                AND rc.CONSTRAINT_SCHEMA = kcu.TABLE_SCHEMA
            WHERE kcu.TABLE_SCHEMA = ? AND kcu.TABLE_NAME = ?
              AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
        ", [$dbName, $table]);

        return collect($rows)->map(fn ($r) => (array) $r);
    }

    public function getIndexes(string $table): Collection
    {
        $rows = DB::select("SHOW INDEX FROM `{$table}`");

        return collect($rows)
            ->groupBy('Key_name')
            ->map(fn ($group, $name) => [
                'name'    => $name,
                'columns' => $group->pluck('Column_name')->toArray(),
                'unique'  => ! $group->first()->Non_unique,
                'primary' => $name === 'PRIMARY',
            ])
            ->values();
    }

    public function buildAlterDdl(string $table, array $changes): string
    {
        $parts = [];

        foreach ($changes['modify'] ?? [] as $col) {
            $parts[] = "  MODIFY COLUMN " . $this->buildColumnDef($col);
        }

        foreach ($changes['add'] ?? [] as $col) {
            $after = ! empty($col['after']) ? " AFTER `{$col['after']}`" : '';
            $parts[] = "  ADD COLUMN " . $this->buildColumnDef($col) . $after;
        }

        foreach ($changes['drop'] ?? [] as $colName) {
            $parts[] = "  DROP COLUMN `{$colName}`";
        }

        if (empty($parts)) {
            return '';
        }

        return "ALTER TABLE `{$table}`\n" . implode(",\n", $parts) . ";";
    }

    public function buildCreateDdl(string $table, array $columns, array $fks, array $options = []): string
    {
        $lines = [];

        foreach ($columns as $col) {
            $lines[] = '  ' . $this->buildColumnDef($col);
        }

        $pkCols = collect($columns)->where('primary', true)->pluck('name');
        if ($pkCols->count() > 1) {
            $lines[] = '  PRIMARY KEY (' . $pkCols->map(fn ($c) => "`{$c}`")->implode(', ') . ')';
        }

        foreach ($fks as $fk) {
            $lines[] = sprintf(
                '  FOREIGN KEY (`%s`) REFERENCES `%s` (`%s`) ON DELETE %s ON UPDATE %s',
                $fk['column'],
                $fk['referenced_table'],
                $fk['referenced_column'],
                $fk['on_delete'] ?? 'RESTRICT',
                $fk['on_update'] ?? 'RESTRICT',
            );
        }

        $engine    = $options['engine']    ?? 'InnoDB';
        $charset   = $options['charset']   ?? 'utf8mb4';
        $collation = $options['collation'] ?? 'utf8mb4_unicode_ci';
        $comment   = ! empty($options['comment'])
            ? " COMMENT='" . addslashes($options['comment']) . "'"
            : '';

        return sprintf(
            "CREATE TABLE `%s` (\n%s\n) ENGINE=%s DEFAULT CHARSET=%s COLLATE=%s%s;",
            $table,
            implode(",\n", $lines),
            $engine, $charset, $collation, $comment
        );
    }

    public function supportsFeature(string $feature): bool
    {
        return match ($feature) {
            'browse_records', 'create_table', 'alter_table',
            'foreign_keys', 'drop_column', 'table_stats' => true,
            default => false,
        };
    }

    private function buildColumnDef(array $col): string
    {
        $type = strtoupper($col['type']);
        $def  = "`{$col['name']}` {$type}";

        if (! empty($col['length'])) {
            $def .= "({$col['length']})";
        }

        if (! empty($col['unsigned'])) {
            $def .= ' UNSIGNED';
        }

        $def .= empty($col['nullable']) ? ' NOT NULL' : ' NULL';

        if ($col['default'] !== null && $col['default'] !== '') {
            $def .= " DEFAULT '" . addslashes($col['default']) . "'";
        }

        if (! empty($col['auto_increment'])) {
            $def .= ' AUTO_INCREMENT';
        }

        if (! empty($col['primary']) && empty($col['composite_pk'])) {
            $def .= ' PRIMARY KEY';
        }

        return $def;
    }
}
```

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/pest tests/Unit/Drivers/MySqlDriverTest.php -v
```

Expected: All PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Drivers/MySqlDriver.php tests/Unit/Drivers/MySqlDriverTest.php
git commit -m "feat: add MySqlDriver with unit tests"
```

---

## Task 7: PostgresDriver

**Files:**
- Create: `src/Drivers/PostgresDriver.php`

> PostgreSQL integration tests require a live Postgres instance and are not included in automated CI. The driver follows the same contract structure as MySQL and SQLite.

- [ ] **Step 1: Create `src/Drivers/PostgresDriver.php`**

```php
<?php

namespace LucaPellegrino\DbMyAdmin\Drivers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;

class PostgresDriver implements DatabaseDriver
{
    public function getTables(): Collection
    {
        $rows = DB::select("
            SELECT
                t.table_name                                 AS name,
                s.n_live_tup                                 AS rows,
                pg_relation_size(quote_ident(t.table_name))  AS data_length,
                pg_indexes_size(quote_ident(t.table_name))   AS index_length,
                NULL                                         AS engine,
                pg_encoding_to_char(d.encoding)              AS collation
            FROM information_schema.tables t
            JOIN pg_stat_user_tables s ON s.relname = t.table_name
            JOIN pg_database d ON d.datname = current_database()
            WHERE t.table_schema = 'public'
              AND t.table_type   = 'BASE TABLE'
            ORDER BY t.table_name
        ");

        return collect($rows)->map(fn ($r) => (array) $r);
    }

    public function getColumns(string $table): Collection
    {
        $rows = DB::select("
            SELECT
                c.column_name                               AS name,
                c.udt_name                                  AS type,
                c.is_nullable                               AS nullable,
                c.column_default                            AS default,
                CASE WHEN pk.column_name IS NOT NULL
                     THEN 'PRI' ELSE '' END                 AS key,
                CASE WHEN c.column_default LIKE 'nextval%'
                     THEN 'auto_increment' ELSE '' END      AS extra,
                c.character_maximum_length                  AS length
            FROM information_schema.columns c
            LEFT JOIN (
                SELECT kcu.column_name
                FROM information_schema.table_constraints tc
                JOIN information_schema.key_column_usage kcu
                    ON tc.constraint_name = kcu.constraint_name
                WHERE tc.constraint_type = 'PRIMARY KEY'
                  AND tc.table_name = ?
                  AND tc.table_schema = 'public'
            ) pk ON pk.column_name = c.column_name
            WHERE c.table_name = ? AND c.table_schema = 'public'
            ORDER BY c.ordinal_position
        ", [$table, $table]);

        return collect($rows)->map(function ($r) {
            $row             = (array) $r;
            $row['type']     = strtoupper($row['type']);
            $row['nullable'] = $row['nullable'] === 'YES';
            return $row;
        });
    }

    public function getForeignKeys(string $table): Collection
    {
        $rows = DB::select("
            SELECT
                kcu.column_name   AS column,
                ccu.table_name    AS referenced_table,
                ccu.column_name   AS referenced_column,
                rc.delete_rule    AS on_delete,
                rc.update_rule    AS on_update
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu
                ON tc.constraint_name = kcu.constraint_name
            JOIN information_schema.referential_constraints rc
                ON rc.constraint_name = tc.constraint_name
            JOIN information_schema.constraint_column_usage ccu
                ON ccu.constraint_name = tc.constraint_name
            WHERE tc.constraint_type = 'FOREIGN KEY'
              AND tc.table_name      = ?
              AND tc.table_schema    = 'public'
        ", [$table]);

        return collect($rows)->map(fn ($r) => (array) $r);
    }

    public function getIndexes(string $table): Collection
    {
        $rows = DB::select("
            SELECT
                i.relname                                   AS name,
                array_to_string(array_agg(a.attname), ',') AS columns,
                ix.indisunique                              AS unique,
                ix.indisprimary                             AS primary
            FROM pg_index ix
            JOIN pg_class t  ON t.oid = ix.indrelid
            JOIN pg_class i  ON i.oid = ix.indexrelid
            JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ANY(ix.indkey)
            WHERE t.relname = ?
            GROUP BY i.relname, ix.indisunique, ix.indisprimary
        ", [$table]);

        return collect($rows)->map(fn ($r) => [
            'name'    => $r->name,
            'columns' => explode(',', $r->columns),
            'unique'  => (bool) $r->unique,
            'primary' => (bool) $r->primary,
        ]);
    }

    public function buildAlterDdl(string $table, array $changes): string
    {
        $parts = [];

        foreach ($changes['modify'] ?? [] as $col) {
            $type = strtoupper($col['type']);
            if (! empty($col['length'])) {
                $type .= "({$col['length']})";
            }
            $parts[] = "  ALTER COLUMN \"{$col['name']}\" TYPE {$type}";
            $nullOp  = empty($col['nullable']) ? 'SET NOT NULL' : 'DROP NOT NULL';
            $parts[] = "  ALTER COLUMN \"{$col['name']}\" {$nullOp}";
            if ($col['default'] !== null && $col['default'] !== '') {
                $parts[] = "  ALTER COLUMN \"{$col['name']}\" SET DEFAULT '" . addslashes($col['default']) . "'";
            }
        }

        foreach ($changes['add'] ?? [] as $col) {
            $parts[] = "  ADD COLUMN " . $this->buildColumnDef($col);
        }

        foreach ($changes['drop'] ?? [] as $colName) {
            $parts[] = "  DROP COLUMN \"{$colName}\"";
        }

        if (empty($parts)) {
            return '';
        }

        return "ALTER TABLE \"{$table}\"\n" . implode(",\n", $parts) . ";";
    }

    public function buildCreateDdl(string $table, array $columns, array $fks, array $options = []): string
    {
        $lines = [];

        foreach ($columns as $col) {
            $lines[] = '  ' . $this->buildColumnDef($col);
        }

        foreach ($fks as $fk) {
            $lines[] = sprintf(
                '  FOREIGN KEY ("%s") REFERENCES "%s" ("%s") ON DELETE %s ON UPDATE %s',
                $fk['column'],
                $fk['referenced_table'],
                $fk['referenced_column'],
                $fk['on_delete'] ?? 'RESTRICT',
                $fk['on_update'] ?? 'RESTRICT',
            );
        }

        return sprintf("CREATE TABLE \"%s\" (\n%s\n);", $table, implode(",\n", $lines));
    }

    public function supportsFeature(string $feature): bool
    {
        return match ($feature) {
            'browse_records', 'create_table', 'alter_table',
            'foreign_keys', 'drop_column', 'table_stats' => true,
            default => false,
        };
    }

    private function buildColumnDef(array $col): string
    {
        $type = strtoupper($col['type']);

        $type = match ($type) {
            'INT', 'INTEGER'   => empty($col['auto_increment']) ? 'INTEGER'  : 'SERIAL',
            'BIGINT'           => empty($col['auto_increment']) ? 'BIGINT'   : 'BIGSERIAL',
            'TINYINT'          => 'SMALLINT',
            'DATETIME'         => 'TIMESTAMP',
            'LONGTEXT', 'MEDIUMTEXT' => 'TEXT',
            default            => $type,
        };

        $textTypes = ['TEXT', 'SERIAL', 'BIGSERIAL', 'INTEGER', 'BIGINT', 'SMALLINT'];
        $def = "\"{$col['name']}\" {$type}";

        if (! empty($col['length']) && ! in_array($type, $textTypes)) {
            $def .= "({$col['length']})";
        }

        if (empty($col['nullable'])) {
            $def .= ' NOT NULL';
        }

        if ($col['default'] !== null && $col['default'] !== '') {
            $def .= " DEFAULT '" . addslashes($col['default']) . "'";
        }

        if (! empty($col['primary']) && empty($col['composite_pk'])) {
            $def .= ' PRIMARY KEY';
        }

        return $def;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Drivers/PostgresDriver.php
git commit -m "feat: add PostgresDriver"
```

---

## Task 8: SavedQuery model

**Files:**
- Create: `src/Models/SavedQuery.php`
- Create: `tests/Unit/Models/SavedQueryTest.php`

- [ ] **Step 1: Create `tests/Unit/Models/SavedQueryTest.php`**

```php
<?php

use LucaPellegrino\DbMyAdmin\Models\SavedQuery;

it('uses the configured table name', function () {
    expect((new SavedQuery())->getTable())->toBe('dbmyadmin_saved_queries');
});

it('truncates long SQL in preview', function () {
    $model      = new SavedQuery();
    $model->sql = str_repeat('SELECT * FROM users WHERE id = 1 AND name = ', 5);

    expect($model->sql_preview)->toHaveLength(80)
                               ->toEndWith('...');
});

it('returns full SQL when short', function () {
    $model      = new SavedQuery();
    $model->sql = 'SELECT 1';

    expect($model->sql_preview)->toBe('SELECT 1');
});

it('can be persisted and retrieved', function () {
    $query = SavedQuery::create([
        'name'       => 'Test Query',
        'sql'        => 'SELECT * FROM users',
        'created_by' => 'test@example.com',
    ]);

    expect($query->id)->toBeInt();
    expect(SavedQuery::find($query->id)->name)->toBe('Test Query');
});
```

- [ ] **Step 2: Run to verify failure**

```bash
./vendor/bin/pest tests/Unit/Models/SavedQueryTest.php -v
```

Expected: FAIL — `SavedQuery` class not found.

- [ ] **Step 3: Create `src/Models/SavedQuery.php`**

```php
<?php

namespace LucaPellegrino\DbMyAdmin\Models;

use Illuminate\Database\Eloquent\Model;

class SavedQuery extends Model
{
    protected $fillable = ['name', 'description', 'sql', 'created_by'];

    public function getTable(): string
    {
        return config('dbmyadmin.saved_queries_table', 'dbmyadmin_saved_queries');
    }

    public function getSqlPreviewAttribute(): string
    {
        if (strlen($this->sql) <= 80) {
            return $this->sql;
        }

        return substr($this->sql, 0, 77) . '...';
    }
}
```

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/pest tests/Unit/Models/SavedQueryTest.php -v
```

Expected: All PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Models/SavedQuery.php tests/Unit/Models/SavedQueryTest.php
git commit -m "feat: add SavedQuery model with tests"
```

---

## Task 9: DynamicTableModel + DatabaseTable virtual model

**Files:**
- Create: `src/Models/DynamicTableModel.php`
- Create: `src/Models/DatabaseTable.php`
- Create: `tests/Unit/Models/DynamicTableModelTest.php`

The original code used a PHP `eval()` call to create an anonymous Eloquent model bound to a table name at runtime. This task replaces it with `DynamicTableModel`, a concrete class that sets its table name from the constructor.

- [ ] **Step 1: Create `tests/Unit/Models/DynamicTableModelTest.php`**

```php
<?php

use Illuminate\Support\Facades\Schema;
use LucaPellegrino\DbMyAdmin\Models\DynamicTableModel;

beforeEach(function () {
    Schema::create('articles', fn ($t) => $t->id()->string('title')->timestamps());
});

afterEach(function () {
    Schema::dropIfExists('articles');
});

it('sets the table name from constructor', function () {
    $model = new DynamicTableModel('articles');
    expect($model->getTable())->toBe('articles');
});

it('can query the dynamic table', function () {
    $model = new DynamicTableModel('articles');
    $count = $model->newQuery()->count();
    expect($count)->toBe(0);
});
```

- [ ] **Step 2: Run to verify failure**

```bash
./vendor/bin/pest tests/Unit/Models/DynamicTableModelTest.php -v
```

Expected: FAIL — `DynamicTableModel` class not found.

- [ ] **Step 3: Create `src/Models/DynamicTableModel.php`**

```php
<?php

namespace LucaPellegrino\DbMyAdmin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A concrete Eloquent model whose table name is set at construction time.
 * Used by BrowseTableRecords to query any table dynamically
 * without code generation or runtime class evaluation.
 */
class DynamicTableModel extends Model
{
    public $timestamps = false;

    public function __construct(string $tableName, array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table        = $tableName;
        $this->primaryKey   = 'id';
        $this->incrementing = true;
    }
}
```

- [ ] **Step 4: Create `src/Models/DatabaseTable.php`**

```php
<?php

namespace LucaPellegrino\DbMyAdmin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;

class DatabaseTable extends Model
{
    protected $connection   = null;
    protected $primaryKey   = 'name';
    protected $keyType      = 'string';
    public    $incrementing = false;
    public    $timestamps   = false;

    protected $fillable = ['name', 'rows', 'data_length', 'index_length', 'engine', 'collation'];

    protected $casts = [
        'rows'         => 'integer',
        'data_length'  => 'integer',
        'index_length' => 'integer',
    ];

    protected static ?Collection $cachedModels = null;

    public static function getAllModels(): Collection
    {
        if (static::$cachedModels !== null) {
            return static::$cachedModels;
        }

        /** @var DatabaseDriver $driver */
        $driver   = app(DatabaseDriver::class);
        $excluded = config('dbmyadmin.excluded_tables', []);

        static::$cachedModels = $driver->getTables()
            ->reject(fn ($table) => in_array($table['name'], $excluded))
            ->map(function ($table) {
                $model = new static($table);
                $total = ($table['data_length'] ?? 0) + ($table['index_length'] ?? 0);
                $model->setAttribute('total_size', $total ?: null);
                return $model;
            });

        return static::$cachedModels;
    }

    public static function clearCache(): void
    {
        static::$cachedModels = null;
    }

    public static function find($key): ?static
    {
        return static::getAllModels()->firstWhere('name', $key);
    }

    public static function findOrFail($key): static
    {
        return static::find($key) ?? throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
            "Table [{$key}] not found."
        );
    }

    public static function all($columns = ['*']): Collection
    {
        return static::getAllModels();
    }

    // Prevent actual DB writes
    public function save(array $options = []): bool { return true; }
    public function delete(): ?bool { return true; }
    public function refresh(): static { return $this; }

    public function getConnection(): \Illuminate\Database\Connection
    {
        return DB::connection();
    }
}
```

- [ ] **Step 5: Run tests**

```bash
./vendor/bin/pest tests/Unit/Models/ -v
```

Expected: All PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Models/DynamicTableModel.php src/Models/DatabaseTable.php \
        tests/Unit/Models/DynamicTableModelTest.php
git commit -m "feat: add DynamicTableModel and DatabaseTable virtual model"
```

---

## Task 10: DatabaseTableResource + ListDatabaseTables

**Files:**
- Create: `src/Resources/DatabaseTableResource.php`
- Create: `src/Resources/DatabaseTableResource/Pages/ListDatabaseTables.php`
- Create: `resources/views/pages/list-database-tables.blade.php`

> Verify Filament 5 `Resource`, `Table`, `Action`, and `BulkAction` API against official docs before writing.

- [ ] **Step 1: Create `src/Resources/DatabaseTableResource/Pages/ListDatabaseTables.php`**

```php
<?php

namespace LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use LucaPellegrino\DbMyAdmin\Models\DatabaseTable;
use LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource;

class ListDatabaseTables extends ListRecords
{
    protected static string $resource = DatabaseTableResource::class;

    protected function getTableQuery(): Builder
    {
        $items = DatabaseTable::getAllModels();

        // Anonymous builder that wraps a Collection — no actual DB query.
        return new class($items) extends Builder {
            public function __construct(private Collection $items)
            {
                // Skip parent constructor — all methods are overridden.
            }

            public function get($columns = ['*']): \Illuminate\Database\Eloquent\Collection
            {
                return \Illuminate\Database\Eloquent\Collection::make($this->items);
            }

            public function count($columns = '*'): int
            {
                return $this->items->count();
            }

            public function paginate($perPage = 15, $columns = ['*'], $pageName = 'page', $page = null): LengthAwarePaginator
            {
                $page  = $page ?? Paginator::resolveCurrentPage($pageName);
                $items = $this->items->forPage($page, $perPage);

                return new LengthAwarePaginator(
                    $items,
                    $this->items->count(),
                    $perPage,
                    $page,
                    ['path' => Paginator::resolveCurrentPath()]
                );
            }

            public function orderBy($column, $direction = 'asc'): static
            {
                $this->items = $direction === 'desc'
                    ? $this->items->sortByDesc($column)
                    : $this->items->sortBy($column);
                return $this;
            }

            public function where($column, $operator = null, $value = null, $boolean = 'and'): static
            {
                if (is_string($column) && is_string($value)) {
                    $this->items = $this->items->filter(
                        fn ($item) => str_contains(
                            strtolower((string) ($item->$column ?? '')),
                            strtolower($value)
                        )
                    );
                }
                return $this;
            }

            public function limit($value): static { return $this; }
            public function offset($value): static { return $this; }
            public function with($relations, $callback = null): static { return $this; }
            public function withCount($relations): static { return $this; }
            public function getModel(): \Illuminate\Database\Eloquent\Model { return new DatabaseTable(); }
            public function toBase(): \Illuminate\Database\Query\Builder
            {
                return \Illuminate\Support\Facades\DB::table('information_schema.tables');
            }
        };
    }
}
```

- [ ] **Step 2: Create `src/Resources/DatabaseTableResource.php`**

Migrate from root `DatabaseTableResource.php` with these changes:
- Namespace to `LucaPellegrino\DbMyAdmin\Resources`
- All model/page imports updated
- Views: `dbmyadmin::table-structure`
- Navigation group/icon read from plugin
- `canViewAny()` checks plugin `isAuthorized()` gate

```php
<?php

namespace LucaPellegrino\DbMyAdmin\Resources;

use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
use LucaPellegrino\DbMyAdmin\Models\DatabaseTable;
use LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource\Pages;

// ⚠️ Verify Filament 5 Resource, Table, Action, BulkAction API before finalizing.

class DatabaseTableResource extends Resource
{
    protected static ?string $model          = DatabaseTable::class;
    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';
    protected static ?string $navigationLabel = 'Database Tables';
    protected static ?string $slug           = 'database-tables';

    public static function getNavigationGroup(): ?string
    {
        try {
            return \Filament\Facades\Filament::getCurrentPanel()
                ->getPlugin('dbmyadmin')
                ->getNavigationGroup();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function canViewAny(): bool
    {
        try {
            if (! \Filament\Facades\Filament::getCurrentPanel()->getPlugin('dbmyadmin')->isAuthorized()) {
                return false;
            }
        } catch (\Throwable) {
            // Plugin not found via panel — allow by default
        }
        return true;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Table')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('rows')
                    ->label('Rows')
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('data_length')
                    ->label('Data Size')
                    ->formatStateUsing(fn ($state) => static::formatBytes($state))
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('index_length')
                    ->label('Index Size')
                    ->formatStateUsing(fn ($state) => static::formatBytes($state))
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('engine')->label('Engine')->placeholder('—'),
                Tables\Columns\TextColumn::make('collation')->label('Collation')->placeholder('—'),
            ])
            ->actions([
                Tables\Actions\Action::make('browse')
                    ->label('Browse')
                    ->icon('heroicon-o-table-cells')
                    ->url(fn ($record) => Pages\BrowseTableRecords::getUrl(['record' => $record->name])),

                Tables\Actions\Action::make('view_structure')
                    ->label('Structure')
                    ->icon('heroicon-o-list-bullet')
                    ->modalContent(fn ($record) => view('dbmyadmin::table-structure', [
                        'columns' => app(DatabaseDriver::class)->getColumns($record->name),
                        'table'   => $record->name,
                    ]))
                    ->modalHeading(fn ($record) => "Structure: {$record->name}")
                    ->modalSubmitAction(false),

                Tables\Actions\Action::make('alter')
                    ->label('Alter')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn ($record) => Pages\AlterTable::getUrl(['record' => $record->name])),

                Tables\Actions\Action::make('truncate')
                    ->label('Truncate')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Truncate table?')
                    ->modalDescription(fn ($record) => "All records in '{$record->name}' will be permanently deleted.")
                    ->action(function ($record) {
                        DB::table($record->name)->truncate();
                        DatabaseTable::clearCache();
                        static::logOperation("Truncated table: {$record->name}");
                        \Filament\Notifications\Notification::make()
                            ->title("Table '{$record->name}' truncated")
                            ->success()
                            ->send();
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('create_table')
                    ->label('Create Table')
                    ->icon('heroicon-o-plus')
                    ->url(fn () => Pages\CreateTable::getUrl()),

                Tables\Actions\Action::make('sql_runner')
                    ->label('SQL Runner')
                    ->icon('heroicon-o-command-line')
                    ->url(fn () => Pages\SqlQueryRunner::getUrl()),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('truncate_selected')
                    ->label('Truncate Selected')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        foreach ($records as $record) {
                            DB::table($record->name)->truncate();
                        }
                        DatabaseTable::clearCache();
                        static::logOperation('Bulk truncated: ' . $records->pluck('name')->implode(', '));
                        \Filament\Notifications\Notification::make()
                            ->title('Selected tables truncated')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'        => Pages\ListDatabaseTables::route('/'),
            'browse'       => Pages\BrowseTableRecords::route('/{record}/browse'),
            'create-table' => Pages\CreateTable::route('/create-table'),
            'alter-table'  => Pages\AlterTable::route('/{record}/alter'),
            'sql-runner'   => Pages\SqlQueryRunner::route('/sql-runner'),
        ];
    }

    public static function formatBytes(?int $bytes, int $precision = 2): string
    {
        if ($bytes === null) return '—';
        if ($bytes === 0)    return '0 B';

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pow   = (int) floor(log($bytes, 1024));

        return round($bytes / (1024 ** $pow), $precision) . ' ' . $units[$pow];
    }

    protected static function logOperation(string $message): void
    {
        if (config('dbmyadmin.logging', true)) {
            $user = auth()->user()?->email ?? 'unknown';
            Log::info("[DbMyAdmin] [{$user}] {$message}");
        }
    }
}
```

- [ ] **Step 3: Create `resources/views/pages/list-database-tables.blade.php`**

```blade
<x-filament-panels::page>
    {{ $this->table }}
</x-filament-panels::page>
```

- [ ] **Step 4: Commit**

```bash
git add src/Resources/ resources/views/pages/list-database-tables.blade.php
git commit -m "feat: add DatabaseTableResource and ListDatabaseTables"
```

---

## Task 11: BrowseTableRecords page + view

**Files:**
- Create: `src/Resources/DatabaseTableResource/Pages/BrowseTableRecords.php`
- Create: `resources/views/pages/browse-table-records.blade.php`

Migrate from root `BrowseTableRecords.php`. Key change: replace the anonymous-class-via-string-generation pattern with `DynamicTableModel`.

- [ ] **Step 1: Create `src/Resources/DatabaseTableResource/Pages/BrowseTableRecords.php`**

Copy from root `BrowseTableRecords.php` and apply ALL of these changes:

**Namespace and imports:**
```php
<?php

namespace LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource\Pages;

use Filament\Resources\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Forms;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
use LucaPellegrino\DbMyAdmin\Models\DynamicTableModel;
use LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource;
```

**Replace the anonymous-model builder:**

Find the `getAnonymousModel()` method in the original and replace it with:

```php
protected function getAnonymousModel(): DynamicTableModel
{
    return new DynamicTableModel($this->tableName);
}
```

**Replace `information_schema` queries in `mount()`:**

```php
public function mount(string $record): void
{
    $this->tableName    = $record;
    $driver             = app(DatabaseDriver::class);
    $this->tableColumns = $driver->getColumns($record)->toArray();
    $this->detectForeignKeys($driver);
}

protected function detectForeignKeys(DatabaseDriver $driver): void
{
    $this->detectedForeignKeys = $driver->getForeignKeys($this->tableName)
        ->keyBy('column')
        ->toArray();
}
```

**View reference:**
```php
protected static string $view = 'dbmyadmin::pages.browse-table-records';
```

**Keep all other methods** from the original file:
- `getFkConfig()`, `getFkOptions()`, `resolveFkLabel()`
- `buildQuery()`, `table()`, `buildTableColumns()`
- `buildFormSchema()`, `buildFormField()`
- `performCreate()`, `performUpdate()`, `performDelete()`
- `getTitle()`, `getBreadcrumbs()`, `getHeaderActions()`

**Verify Livewire 4 syntax** in any `wire:model`, `@entangle`, or `$this->reset()` calls. Check: https://livewire.laravel.com/docs/upgrading

- [ ] **Step 2: Create `resources/views/pages/browse-table-records.blade.php`**

```blade
<x-filament-panels::page>
    {{ $this->table }}
</x-filament-panels::page>
```

- [ ] **Step 3: Commit**

```bash
git add src/Resources/DatabaseTableResource/Pages/BrowseTableRecords.php \
        resources/views/pages/browse-table-records.blade.php
git commit -m "feat: add BrowseTableRecords with DynamicTableModel"
```

---

## Task 12: CreateTable page + view

**Files:**
- Create: `src/Resources/DatabaseTableResource/Pages/CreateTable.php`
- Create: `resources/views/pages/create-table.blade.php`

- [ ] **Step 1: Create `src/Resources/DatabaseTableResource/Pages/CreateTable.php`**

Copy from root `CreateTable.php` and apply ALL of these changes:

**Namespace and imports:**
```php
<?php

namespace LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource\Pages;

use Filament\Resources\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
use LucaPellegrino\DbMyAdmin\Models\DatabaseTable;
use LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource;
```

**View reference:**
```php
protected static string $view = 'dbmyadmin::pages.create-table';
```

**Replace `getAvailableTables()`:**
```php
public function getAvailableTables(): array
{
    return app(DatabaseDriver::class)->getTables()->pluck('name')->toArray();
}
```

**Replace `getColumnsForTable()`:**
```php
public function getColumnsForTable(string $table): array
{
    return app(DatabaseDriver::class)->getColumns($table)->pluck('name')->toArray();
}
```

**Replace `generateDdl()` — delegate to driver:**
```php
public function generateDdl(): void
{
    $driver = app(DatabaseDriver::class);
    $this->generatedDdl = $driver->buildCreateDdl(
        $this->tableName,
        $this->columns,
        $this->foreignKeys,
        [
            'engine'    => $this->engine,
            'charset'   => $this->charset,
            'collation' => $this->collation,
            'comment'   => $this->tableComment,
        ]
    );
}
```

**Replace `createTable()` — add cache clear and logging:**
```php
public function createTable(): void
{
    $this->generateDdl();
    try {
        DB::unprepared($this->generatedDdl);
        DatabaseTable::clearCache();
        if (config('dbmyadmin.logging')) {
            Log::info('[DbMyAdmin] [' . (auth()->user()?->email ?? 'unknown') . "] Created table: {$this->tableName}");
        }
        Notification::make()->title("Table '{$this->tableName}' created")->success()->send();
        $this->redirect(DatabaseTableResource::getUrl());
    } catch (\Throwable $e) {
        Notification::make()->title('Error: ' . $e->getMessage())->danger()->send();
    }
}
```

**Keep** all column/FK helper methods from original: `makeColumn()`, `addColumn()`, `removeColumn()`, `moveColumnUp()`, `moveColumnDown()`, `updatedColumns()`, `makeForeignKey()`, `addForeignKey()`, `removeForeignKey()`, `typeAcceptsLength()`, `isNumericType()`, `formatDefault()`, `getTitle()`, `getBreadcrumbs()`.

**Verify Livewire 4 syntax** throughout.

- [ ] **Step 2: Create `resources/views/pages/create-table.blade.php`**

Copy from root `create-table.blade.php` and apply ALL of these changes:

1. Wrapper tag: `<x-filament-panels::page>` (verify name in Filament 5)
2. Replace ALL hardcoded colors:
   - `bg-blue-*` → `bg-primary-*`
   - `text-blue-*` → `text-primary-*`
   - `text-indigo-*` → `text-primary-*`
   - `border-blue-*` → `border-primary-*`
   - `ring-blue-*` → `ring-primary-*`
   - `focus:ring-blue-*` → `focus:ring-primary-*`
   - Keep `bg-gray-*`, `text-gray-*`, `border-gray-*` as-is
3. Verify `wire:model.live` syntax for Livewire 4

- [ ] **Step 3: Commit**

```bash
git add src/Resources/DatabaseTableResource/Pages/CreateTable.php \
        resources/views/pages/create-table.blade.php
git commit -m "feat: add CreateTable page and view"
```

---

## Task 13: AlterTable page + view

**Files:**
- Create: `src/Resources/DatabaseTableResource/Pages/AlterTable.php`
- Create: `resources/views/pages/alter-table.blade.php`

- [ ] **Step 1: Create `src/Resources/DatabaseTableResource/Pages/AlterTable.php`**

Copy from root `AlterTable.php` and apply ALL of these changes:

**Namespace and imports:**
```php
<?php

namespace LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource\Pages;

use Filament\Resources\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
use LucaPellegrino\DbMyAdmin\Models\DatabaseTable;
use LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource;
```

**View reference:**
```php
protected static string $view = 'dbmyadmin::pages.alter-table';
```

**Replace `loadExistingColumns()`:**
```php
protected function loadExistingColumns(): void
{
    $driver        = app(DatabaseDriver::class);
    $this->columns = $driver->getColumns($this->tableName)
        ->map(fn ($col) => array_merge($col, ['_modified' => false, '_drop' => false]))
        ->toArray();
}
```

**Replace `generateDdl()`:**
```php
public function generateDdl(): void
{
    $driver  = app(DatabaseDriver::class);
    $changes = [
        'modify' => collect($this->columns)->filter(fn ($c) => $c['_modified'] && ! $c['_drop'])->values()->toArray(),
        'add'    => $this->newColumns,
        'drop'   => collect($this->columns)->filter(fn ($c) => $c['_drop'])->pluck('name')->toArray(),
    ];
    $this->generatedDdl = $driver->buildAlterDdl($this->tableName, $changes);
}
```

**Replace `applyChanges()`:**
```php
public function applyChanges(): void
{
    $this->generateDdl();
    if (empty($this->generatedDdl) || str_starts_with(trim($this->generatedDdl), '--')) {
        Notification::make()->title('No applicable changes')->warning()->send();
        return;
    }
    try {
        DB::unprepared($this->generatedDdl);
        DatabaseTable::clearCache();
        if (config('dbmyadmin.logging')) {
            Log::info('[DbMyAdmin] [' . (auth()->user()?->email ?? 'unknown') . "] Altered table: {$this->tableName}");
        }
        Notification::make()->title("Table '{$this->tableName}' altered")->success()->send();
        $this->loadExistingColumns();
        $this->newColumns   = [];
        $this->generatedDdl = '';
    } catch (\Throwable $e) {
        Notification::make()->title('Error: ' . $e->getMessage())->danger()->send();
    }
}
```

**Keep** `markModified()`, `toggleDrop()`, `updatedColumns()`, `addNewColumn()`, `removeNewColumn()`, `updatedNewColumns()`, `getAvailableColumns()`, `getTitle()`, `getBreadcrumbs()`.

**Verify Livewire 4 syntax** throughout.

- [ ] **Step 2: Create `resources/views/pages/alter-table.blade.php`**

Copy from root `alter-table.blade.php` and apply:
1. Wrapper: `<x-filament-panels::page>`
2. Color replacement (same list as Task 12)
3. Verify `wire:model.live` syntax for Livewire 4

- [ ] **Step 3: Commit**

```bash
git add src/Resources/DatabaseTableResource/Pages/AlterTable.php \
        resources/views/pages/alter-table.blade.php
git commit -m "feat: add AlterTable page and view"
```

---

## Task 14: SqlQueryRunner page

**Files:**
- Create: `src/Resources/DatabaseTableResource/Pages/SqlQueryRunner.php`
- Create: `resources/views/pages/sql-query-runner.blade.php`
- Create: `tests/Feature/SqlQueryRunnerBlockedStatementsTest.php`

- [ ] **Step 1: Write security tests**

Create `tests/Feature/SqlQueryRunnerBlockedStatementsTest.php`:

```php
<?php

use Livewire\Livewire;
use LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource\Pages\SqlQueryRunner;

it('blocks DROP statements', function () {
    Livewire::test(SqlQueryRunner::class)
        ->set('query', 'DROP TABLE users')
        ->call('runQuery')
        ->assertSet('errorMessage', fn ($msg) => ! empty($msg));
});

it('blocks TRUNCATE statements', function () {
    Livewire::test(SqlQueryRunner::class)
        ->set('query', 'TRUNCATE users')
        ->call('runQuery')
        ->assertSet('errorMessage', fn ($msg) => ! empty($msg));
});

it('allows SELECT statements', function () {
    Livewire::test(SqlQueryRunner::class)
        ->set('query', 'SELECT 1')
        ->call('runQuery')
        ->assertSet('errorMessage', null);
});

it('blocked_statements list is configurable', function () {
    config(['dbmyadmin.query_runner.blocked_statements' => []]);

    $runner = new SqlQueryRunner();
    expect($runner->isBlockedStatement('DROP TABLE users'))->toBeFalse();
});
```

- [ ] **Step 2: Run tests to verify failure**

```bash
./vendor/bin/pest tests/Feature/SqlQueryRunnerBlockedStatementsTest.php -v
```

Expected: FAIL — `SqlQueryRunner` class not found.

- [ ] **Step 3: Create `src/Resources/DatabaseTableResource/Pages/SqlQueryRunner.php`**

Copy from root `SqlQueryRunner.php` and apply ALL of these changes:

**Namespace and imports:**
```php
<?php

namespace LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource\Pages;

use Filament\Resources\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
use LucaPellegrino\DbMyAdmin\Models\SavedQuery;
use LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource;
```

**View reference:**
```php
protected static string $view = 'dbmyadmin::pages.sql-query-runner';
```

**Replace `buildSchemaMap()`:**
```php
public function buildSchemaMap(): void
{
    $driver          = app(DatabaseDriver::class);
    $this->schemaMap = $driver->getTables()
        ->mapWithKeys(fn ($table) => [
            $table['name'] => $driver->getColumns($table['name'])->pluck('name')->toArray(),
        ])
        ->toArray();
}
```

**Replace `isBlockedStatement()` — use config:**
```php
public function isBlockedStatement(string $sql): bool
{
    $blocked = config('dbmyadmin.query_runner.blocked_statements', []);
    $first   = strtoupper(strtok(trim($sql), " \t\n\r"));
    return in_array($first, array_map('strtoupper', $blocked));
}
```

**In `runQuery()` — use config for max_rows:**
```php
$maxRows    = config('dbmyadmin.query_runner.max_rows', 1000);
$limitedSql = rtrim($sql, ';') . ' LIMIT ' . (int) $maxRows;
```

**Replace `SavedQuery` import** throughout — use `LucaPellegrino\DbMyAdmin\Models\SavedQuery`.

**Verify Livewire 4 syntax** — especially:
- `$this->reset()` → check if signature changed in Livewire 4
- `updatedSearchQuery()` hook syntax
- Any `wire:model` uses in the component itself

**Keep all other methods:** `mount()`, `runQuery()`, `clearQuery()`, `loadSavedQueries()`, `updatedSearchQuery()`, `openSaveModal()`, `closeSaveModal()`, `saveQuery()`, `useSavedQuery()`, `deleteSavedQuery()`, `loadTemplate()`, `getTitle()`, `getBreadcrumbs()`.

- [ ] **Step 4: Run security tests**

```bash
./vendor/bin/pest tests/Feature/SqlQueryRunnerBlockedStatementsTest.php -v
```

Expected: All PASS.

- [ ] **Step 5: Create `resources/views/pages/sql-query-runner.blade.php`**

Copy from root `sql-query-runner.blade.php` and apply ALL of these changes:

1. Wrapper: `<x-filament-panels::page>`
2. Color replacement (same list as Task 12)
3. **Critical — Livewire 4 Alpine integration:** Find all `@entangle('...')` calls and verify syntax against https://livewire.laravel.com/docs/alpine
4. Verify `wire:model.live.debounce.300ms` syntax for Livewire 4
5. Replace any `wire:confirm="..."` attributes — Livewire 4 may have removed this directive. Use an Alpine `x-on:click="if(confirm('...')) $wire.call('...')"` pattern instead

- [ ] **Step 6: Commit**

```bash
git add src/Resources/DatabaseTableResource/Pages/SqlQueryRunner.php \
        resources/views/pages/sql-query-runner.blade.php \
        tests/Feature/SqlQueryRunnerBlockedStatementsTest.php
git commit -m "feat: add SqlQueryRunner with security tests"
```

---

## Task 15: Remaining views

**Files:**
- Create: `resources/views/table-structure.blade.php`

- [ ] **Step 1: Create `resources/views/table-structure.blade.php`**

Copy from root `table-structure.blade.php` and apply:
1. Color replacement (same list as Task 12)
2. This is a static view (no Livewire) — no other changes needed

- [ ] **Step 2: Run full test suite**

```bash
./vendor/bin/pest --no-coverage
```

Expected: All tests PASS.

- [ ] **Step 3: Commit**

```bash
git add resources/views/table-structure.blade.php
git commit -m "feat: add table-structure modal view"
```

---

## Task 16: Integration smoke test

Manually install the package into a fresh Laravel 12 + Filament 5 app to verify end-to-end behavior.

- [ ] **Step 1: Create a test Laravel 12 app outside this repo**

```bash
composer create-project laravel/laravel dbmyadmin-test
cd dbmyadmin-test
```

- [ ] **Step 2: Add local path repository and install package**

In `dbmyadmin-test/composer.json`, add before `"require"`:
```json
"repositories": [
    {
        "type": "path",
        "url": "../lukemyadmin"
    }
]
```

```bash
composer require lucapellegrino/dbmyadmin
```

- [ ] **Step 3: Install Filament 5 and configure panel**

Follow Filament 5 installation guide. Then register the plugin in the panel provider:

```php
use LucaPellegrino\DbMyAdmin\DbMyAdminPlugin;

->plugins([
    DbMyAdminPlugin::make()
        ->navigationGroup('Database'),
])
```

- [ ] **Step 4: Publish and run migrations**

```bash
php artisan vendor:publish --tag=dbmyadmin-migrations
php artisan migrate
php artisan serve
```

- [ ] **Step 5: Manual verification checklist**

- [ ] Table list loads and shows all database tables
- [ ] Excluded tables (migrations, sessions, etc.) are not shown
- [ ] Browse records opens and shows paginated data
- [ ] Create Table form renders and generates DDL
- [ ] Create Table DDL executes and new table appears in list
- [ ] Alter Table loads existing columns and generates DDL
- [ ] SQL Runner executes a `SELECT 1` query
- [ ] SQL Runner blocks `DROP TABLE test`
- [ ] Saved query can be created, loaded, edited, and deleted
- [ ] Schema sidebar in SQL Runner shows tables and columns
- [ ] Plugin colors adapt to the panel's primary color setting
- [ ] Dark mode renders without hardcoded color artifacts
- [ ] Filament Shield (if installed) generates permissions automatically

- [ ] **Step 6: Tag first release**

Back in the package directory:
```bash
cd D:/development/lukemyadmin
git tag v1.0.0
```

Then submit to Packagist at https://packagist.org/packages/submit

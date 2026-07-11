# Connection Manager — Core Extension Points Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Refactor `lucapellegrino/dbmyadmin` (the free Core package) so every database access point routes through a single named connection resolved from `config('dbmyadmin.connection')` instead of assuming the host app's default connection, and expose the extension points (`ActiveConnectionResolver` contract, config-driven driver map, render hooks) that the future `dbmyadmin-pro` package needs — all with **zero behavior change** for users who don't install Pro.

**Architecture:** Introduce one choke-point class, `LucaPellegrino\DbMyAdmin\Support\ConnectionManager`, that every driver/model/page goes through instead of the raw `DB`/`Schema` facades. On first use per request it asks a swappable `ActiveConnectionResolver` (default: a no-op that means "use the app's default connection") whether to activate a different named connection, then every subsequent call in that request targets whichever connection was activated. This is Plan 1 of the "DbMyAdmin Pro — Multi-Connection Manager" initiative (see `docs/superpowers/specs/2026-07-11-connection-manager-pro-design.md`); Plans 2+ (the new `dbmyadmin-pro` repo: Connections CRUD, SSH tunnel manager, SQL Server driver) depend on the classes/interfaces this plan produces and will be written once this lands.

**Tech Stack:** PHP 8.3+, Laravel 12/13, Filament 5, Pest 3, Orchestra Testbench 10.

## Global Constraints

- PHP `^8.3`, Laravel `^12.0|^13.0`, Filament `^5.0` (from `composer.json` — do not lower these).
- `database.default` must never be mutated anywhere in this refactor (see spec §2.2 — TRUNCATE/DROP-capable code must never risk touching the host app's real connection by accident).
- The entire existing Pest suite (55 tests as of this plan) must stay green, unmodified in intent, after every task — this refactor must be behavior-preserving when `config('dbmyadmin.connection')` is `null` (the default).
- `LucaPellegrino\DbMyAdmin\Models\SavedQuery` must **not** be touched — saved-query metadata always lives in the host app's own database, never the externally-selected connection (it's package configuration, not target data).
- No Pro/licensing code of any kind belongs in this repo (it's public MIT) — this plan only adds extension points Pro will consume from its own separate private repo.
- Follow the existing static-cache-needs-manual-reset convention already used by `DatabaseTable::clearCache()` (see `tests/Unit/Models/*Test.php` for the pattern) when adding any new static state.

---

### Task 1: Config keys + `ActiveConnectionResolver` contract + `ConnectionManager`

**Files:**
- Modify: `config/dbmyadmin.php`
- Create: `src/Contracts/ActiveConnectionResolver.php`
- Create: `src/Support/NullConnectionResolver.php`
- Create: `src/Support/ConnectionManager.php`
- Test: `tests/Unit/Support/ConnectionManagerTest.php`

**Interfaces:**
- Produces: `LucaPellegrino\DbMyAdmin\Contracts\ActiveConnectionResolver::resolve(): ?array`
- Produces: `LucaPellegrino\DbMyAdmin\Support\ConnectionManager::connection(): \Illuminate\Database\Connection`
- Produces: `LucaPellegrino\DbMyAdmin\Support\ConnectionManager::schema(): \Illuminate\Database\Schema\Builder`
- Produces: `LucaPellegrino\DbMyAdmin\Support\ConnectionManager::activate(): void`
- Produces: `LucaPellegrino\DbMyAdmin\Support\ConnectionManager::reset(): void` (test-only helper, mirrors `DatabaseTable::clearCache()`)
- Produces: `config('dbmyadmin.connection')` (nullable string) and `config('dbmyadmin.drivers')` (array `string => class-string<DatabaseDriver>`)

- [ ] **Step 1: Add the new config keys**

Edit `config/dbmyadmin.php`. Replace the top of the file (the `'driver' => 'auto',` block) with:

```php
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
```

Leave the rest of the file (`excluded_tables`, `query_runner`, `saved_queries_table`, `logging`) exactly as-is below this block.

- [ ] **Step 2: Create the `ActiveConnectionResolver` contract**

Create `src/Contracts/ActiveConnectionResolver.php`:

```php
<?php

namespace LucaPellegrino\DbMyAdmin\Contracts;

interface ActiveConnectionResolver
{
    /**
     * Return a Laravel database connection config array (see config/database.php
     * connection shapes) to activate for the current request, or null to use
     * the host application's default connection.
     */
    public function resolve(): ?array;
}
```

- [ ] **Step 3: Create the default no-op resolver**

Create `src/Support/NullConnectionResolver.php`:

```php
<?php

namespace LucaPellegrino\DbMyAdmin\Support;

use LucaPellegrino\DbMyAdmin\Contracts\ActiveConnectionResolver;

class NullConnectionResolver implements ActiveConnectionResolver
{
    public function resolve(): ?array
    {
        return null;
    }
}
```

- [ ] **Step 4: Write the failing test for `ConnectionManager`**

Create `tests/Unit/Support/ConnectionManagerTest.php`:

```php
<?php

use Illuminate\Support\Facades\DB;
use LucaPellegrino\DbMyAdmin\Contracts\ActiveConnectionResolver;
use LucaPellegrino\DbMyAdmin\Support\ConnectionManager;

afterEach(function () {
    ConnectionManager::reset();
});

it('returns the default connection when no resolver override is bound', function () {
    $connection = ConnectionManager::connection();

    expect($connection->getName())->toBe(config('database.default'));
});

it('activates a named connection returned by the bound resolver, only once per request', function () {
    app()->bind(ActiveConnectionResolver::class, function () {
        return new class implements ActiveConnectionResolver {
            public int $calls = 0;

            public function resolve(): ?array
            {
                $this->calls++;

                return [
                    'driver'   => 'sqlite',
                    'database' => ':memory:',
                    'prefix'   => '',
                ];
            }
        };
    });

    ConnectionManager::connection();
    ConnectionManager::connection();

    expect(config('dbmyadmin.connection'))->toBe('dbmyadmin_active');
    expect(config('database.connections.dbmyadmin_active.driver'))->toBe('sqlite');
});

it('reset() allows re-activation on the next call', function () {
    ConnectionManager::connection();
    ConnectionManager::reset();

    app()->bind(ActiveConnectionResolver::class, function () {
        return new class implements ActiveConnectionResolver {
            public function resolve(): ?array
            {
                return ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''];
            }
        };
    });

    ConnectionManager::connection();

    expect(config('dbmyadmin.connection'))->toBe('dbmyadmin_active');
});
```

- [ ] **Step 5: Run the test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Support/ConnectionManagerTest.php`
Expected: FAIL — `Class "LucaPellegrino\DbMyAdmin\Support\ConnectionManager" not found`.

- [ ] **Step 6: Implement `ConnectionManager`**

Create `src/Support/ConnectionManager.php`:

```php
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

        static::$activated = true;

        $config = app(ActiveConnectionResolver::class)->resolve();

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
    }
}
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Support/ConnectionManagerTest.php`
Expected: PASS (3 tests).

- [ ] **Step 8: Run the full suite to confirm no regression**

Run: `vendor/bin/pest`
Expected: PASS (58 tests — 55 existing + 3 new).

- [ ] **Step 9: Commit**

```bash
git add config/dbmyadmin.php src/Contracts/ActiveConnectionResolver.php src/Support/NullConnectionResolver.php src/Support/ConnectionManager.php tests/Unit/Support/ConnectionManagerTest.php
git commit -m "feat: add ActiveConnectionResolver contract and ConnectionManager choke point"
```

---

### Task 2: Refactor `DbMyAdminServiceProvider` — config-driven driver map + resolver binding

**Files:**
- Modify: `src/DbMyAdminServiceProvider.php`
- Test: `tests/Unit/DbMyAdminServiceProviderTest.php` (new)

**Interfaces:**
- Consumes: `ConnectionManager::connection()` (Task 1), `ConnectionManager::reset()` (Task 1), `NullConnectionResolver` (Task 1)
- Produces: `ActiveConnectionResolver` bound in the container (default: `NullConnectionResolver`) — Pro will `$this->app->bind(ActiveConnectionResolver::class, ...)` to override this.
- Produces: `DatabaseDriver` singleton now resolved from `config('dbmyadmin.drivers')` instead of a hardcoded `match`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/DbMyAdminServiceProviderTest.php`:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/DbMyAdminServiceProviderTest.php`
Expected: The first test fails — `ActiveConnectionResolver` isn't bound yet (`BindingResolutionException`).

- [ ] **Step 3: Refactor the service provider**

Edit `src/DbMyAdminServiceProvider.php`. Replace the whole file with:

```php
<?php

namespace LucaPellegrino\DbMyAdmin;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;
use LucaPellegrino\DbMyAdmin\Contracts\ActiveConnectionResolver;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
use LucaPellegrino\DbMyAdmin\Support\ConnectionManager;
use LucaPellegrino\DbMyAdmin\Support\NullConnectionResolver;

class DbMyAdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/dbmyadmin.php', 'dbmyadmin');

        $this->app->bind(ActiveConnectionResolver::class, NullConnectionResolver::class);

        $this->app->singleton(DatabaseDriver::class, function ($app) {
            $configured = config('dbmyadmin.driver', 'auto');
            $driver = $configured === 'auto'
                ? ConnectionManager::connection()->getDriverName()
                : $configured;

            $map = config('dbmyadmin.drivers', []);

            if (! isset($map[$driver])) {
                throw new \RuntimeException(
                    "DbMyAdmin: unsupported database driver [{$driver}]. Supported: " . implode(', ', array_keys($map)) . "."
                );
            }

            return $app->make($map[$driver]);
        });
    }

    public function boot(): void
    {
        FilamentAsset::register([
            Css::make('dbmyadmin-styles', __DIR__ . '/../dist/dbmyadmin.css'),
        ], package: 'lucapellegrino/dbmyadmin');

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

Note what changed: the `match` on the driver name is gone (was throwing directly for unsupported drivers with a hardcoded "Supported: mysql, pgsql, sqlite" message); it's replaced by a lookup into `config('dbmyadmin.drivers')`, with the same style of exception but a dynamically built "Supported: ..." list. `$app['db']->connection()->getDriverName()` became `ConnectionManager::connection()->getDriverName()` — this is the actual activation trigger (Task 1's `ConnectionManager::activate()` runs here, lazily, the first time `DatabaseDriver` is resolved in a request).

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/pest tests/Unit/DbMyAdminServiceProviderTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Run the full suite**

Run: `vendor/bin/pest`
Expected: PASS (61 tests — 58 + 3 new). If any of the 3 previous `MySqlDriverTest`/similar "unsupported driver" style assertions elsewhere break, they don't exist yet — this is a new failure mode, not a modified one, so nothing else should be affected.

- [ ] **Step 6: Commit**

```bash
git add src/DbMyAdminServiceProvider.php tests/Unit/DbMyAdminServiceProviderTest.php
git commit -m "refactor: resolve DatabaseDriver from a config-driven map, bind ActiveConnectionResolver"
```

---

### Task 3: Named-connection resolution on `DatabaseTable` and `DynamicTableModel`

**Files:**
- Modify: `src/Models/DatabaseTable.php`
- Modify: `src/Models/DynamicTableModel.php`
- Test: `tests/Unit/Models/DatabaseTableConnectionTest.php` (new)
- Test: `tests/Unit/Models/DynamicTableModelTest.php` (extend existing)

**Interfaces:**
- Consumes: `ConnectionManager::connection()` (Task 1)
- Produces: both models' `getConnectionName()` now returns `config('dbmyadmin.connection')` instead of a hardcoded `null`/unset property — this is what makes `DatabaseTable::getAllModels()` and `BrowseTableRecords::buildQuery()` (which instantiates `DynamicTableModel`) respect the active connection.

- [ ] **Step 1: Write the failing test for `DatabaseTable`**

Create `tests/Unit/Models/DatabaseTableConnectionTest.php`:

```php
<?php

use LucaPellegrino\DbMyAdmin\Models\DatabaseTable;

afterEach(function () {
    config(['dbmyadmin.connection' => null]);
});

it('uses the app default connection name when dbmyadmin.connection is null', function () {
    $model = new DatabaseTable();

    expect($model->getConnectionName())->toBeNull();
});

it('uses config(dbmyadmin.connection) as the connection name when set', function () {
    config(['dbmyadmin.connection' => 'dbmyadmin_active']);

    $model = new DatabaseTable();

    expect($model->getConnectionName())->toBe('dbmyadmin_active');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Models/DatabaseTableConnectionTest.php`
Expected: FAIL on the second test — `getConnectionName()` still returns `null` regardless of config (current hardcoded `protected $connection = null;`).

- [ ] **Step 3: Update `DatabaseTable`**

Edit `src/Models/DatabaseTable.php`. Replace:

```php
class DatabaseTable extends Model
{
    protected $connection   = null;
    protected $primaryKey   = 'name';
```

with:

```php
class DatabaseTable extends Model
{
    protected $primaryKey   = 'name';
```

Then replace the `getConnection()` method:

```php
    public function getConnection(): \Illuminate\Database\Connection
    {
        return DB::connection();
    }
```

with:

```php
    public function getConnectionName()
    {
        return config('dbmyadmin.connection');
    }

    public function getConnection(): \Illuminate\Database\Connection
    {
        return ConnectionManager::connection();
    }
```

Update the `use` block at the top of the file — replace:

```php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
```

with:

```php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
use LucaPellegrino\DbMyAdmin\Support\ConnectionManager;
```

(The `DB` facade import is now unused in this file — every other method already goes through the injected `DatabaseDriver`.)

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Models/DatabaseTableConnectionTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Write the failing test for `DynamicTableModel`**

Read the existing `tests/Unit/Models/DynamicTableModelTest.php` first, then add this test to it (append, don't replace the file):

```php
it('uses config(dbmyadmin.connection) as the connection name', function () {
    config(['dbmyadmin.connection' => 'dbmyadmin_active']);

    $model = new DynamicTableModel('test_users');

    expect($model->getConnectionName())->toBe('dbmyadmin_active');

    config(['dbmyadmin.connection' => null]);
});
```

- [ ] **Step 6: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Models/DynamicTableModelTest.php`
Expected: FAIL — `getConnectionName()` returns `null` regardless of config (no override yet).

- [ ] **Step 7: Update `DynamicTableModel`**

Edit `src/Models/DynamicTableModel.php`. Replace the whole file with:

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

    public function __construct(string $tableName = '', string $primaryKey = 'id', array $attributes = [])
    {
        parent::__construct($attributes);
        if ($tableName !== '') {
            $this->table = $tableName;
        }
        $this->primaryKey   = $primaryKey;
        $this->incrementing = true;
    }

    public function getConnectionName()
    {
        return config('dbmyadmin.connection');
    }
}
```

- [ ] **Step 8: Run the test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Models/DynamicTableModelTest.php`
Expected: PASS (3 tests — 2 existing + 1 new).

- [ ] **Step 9: Run the full suite**

Run: `vendor/bin/pest`
Expected: PASS (64 tests).

- [ ] **Step 10: Commit**

```bash
git add src/Models/DatabaseTable.php src/Models/DynamicTableModel.php tests/Unit/Models/DatabaseTableConnectionTest.php tests/Unit/Models/DynamicTableModelTest.php
git commit -m "refactor: resolve DatabaseTable/DynamicTableModel connection name from config('dbmyadmin.connection')"
```

---

### Task 4: Refactor `MySqlDriver` to use `ConnectionManager`

**Files:**
- Modify: `src/Drivers/MySqlDriver.php`

**Interfaces:**
- Consumes: `ConnectionManager::connection()` (Task 1)
- Produces: no change to `DatabaseDriver` contract — purely internal.

**Note:** the existing `tests/Unit/Drivers/MySqlDriverTest.php` never exercises `getTables()`/`getColumns()`/`getForeignKeys()`/`getIndexes()` against a live MySQL server (only the pure DDL-string methods and `supportsFeature`/`implements` checks — confirmed by reading the file). There is no live-MySQL test infrastructure in this suite, so this task's only regression signal is the existing suite staying green; no new test is added here (a genuinely meaningful "respects the named connection" proof for a live driver happens in Task 6, against SQLite, and again end-to-end in Task 13).

- [ ] **Step 1: Update the `use` block**

Edit `src/Drivers/MySqlDriver.php`. Replace:

```php
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
```

with:

```php
use Illuminate\Support\Collection;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
use LucaPellegrino\DbMyAdmin\Support\ConnectionManager;
```

- [ ] **Step 2: Replace every `DB::` call site**

In `getTables()`, replace:

```php
        $dbName = DB::connection()->getDatabaseName();
        $rows   = DB::select("
```

with:

```php
        $dbName = ConnectionManager::connection()->getDatabaseName();
        $rows   = ConnectionManager::connection()->select("
```

Apply the identical two-line substitution in `getColumns()` and `getForeignKeys()` (both currently start with `$dbName = DB::connection()->getDatabaseName();` followed by `$rows   = DB::select("`).

In `getIndexes()`, replace:

```php
        $rows = DB::select("SHOW INDEX FROM `{$table}`");
```

with:

```php
        $rows = ConnectionManager::connection()->select("SHOW INDEX FROM `{$table}`");
```

`buildAlterDdl`, `buildCreateDdl`, `supportsFeature`, and `buildColumnDef` are pure string builders with no `DB::` calls — leave them untouched.

- [ ] **Step 3: Run the driver's existing tests**

Run: `vendor/bin/pest tests/Unit/Drivers/MySqlDriverTest.php`
Expected: PASS (unchanged — these tests don't touch the modified methods, they just confirm nothing broke syntactically/structurally).

- [ ] **Step 4: Run the full suite**

Run: `vendor/bin/pest`
Expected: PASS (64 tests, no change in count).

- [ ] **Step 5: Commit**

```bash
git add src/Drivers/MySqlDriver.php
git commit -m "refactor: route MySqlDriver queries through ConnectionManager"
```

---

### Task 5: Refactor `PostgresDriver` to use `ConnectionManager`

**Files:**
- Modify: `src/Drivers/PostgresDriver.php`

**Interfaces:**
- Consumes: `ConnectionManager::connection()` (Task 1)

Same rationale as Task 4 — no new test, existing suite is the regression signal.

- [ ] **Step 1: Update the `use` block**

Edit `src/Drivers/PostgresDriver.php`. Replace:

```php
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
```

with:

```php
use Illuminate\Support\Collection;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
use LucaPellegrino\DbMyAdmin\Support\ConnectionManager;
```

- [ ] **Step 2: Replace every `DB::select(` call site**

There are four occurrences, in `getTables()`, `getColumns()`, `getForeignKeys()`, and `getIndexes()`, all of the shape:

```php
        $rows = DB::select("
```

Replace each with:

```php
        $rows = ConnectionManager::connection()->select("
```

(Only the call itself changes — the SQL string and bindings that follow are untouched.)

`buildAlterDdl`, `buildCreateDdl`, `supportsFeature`, and `buildColumnDef` have no `DB::` calls — leave untouched.

- [ ] **Step 3: Run the driver's existing tests**

Run: `vendor/bin/pest tests/Unit/Drivers/PostgresDriverTest.php`
Expected: PASS (unchanged).

- [ ] **Step 4: Run the full suite**

Run: `vendor/bin/pest`
Expected: PASS (64 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Drivers/PostgresDriver.php
git commit -m "refactor: route PostgresDriver queries through ConnectionManager"
```

---

### Task 6: Refactor `SqliteDriver` to use `ConnectionManager` + prove named-connection respect

**Files:**
- Modify: `src/Drivers/SqliteDriver.php`
- Test: `tests/Unit/Drivers/SqliteDriverTest.php` (extend existing)

**Interfaces:**
- Consumes: `ConnectionManager::connection()`, `ConnectionManager::reset()` (Task 1)

This is the first driver where a *real* "does the named connection actually get used" test is possible, because the test suite already runs a live SQLite connection (see `tests/TestCase.php`).

- [ ] **Step 1: Update the `use` block**

Edit `src/Drivers/SqliteDriver.php`. Replace:

```php
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
```

with:

```php
use Illuminate\Support\Collection;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
use LucaPellegrino\DbMyAdmin\Support\ConnectionManager;
```

- [ ] **Step 2: Replace every `DB::` call site**

In `getTables()`, replace:

```php
        $tables = DB::select(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
        );
```

with:

```php
        $tables = ConnectionManager::connection()->select(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
        );
```

In `getColumns()`, replace:

```php
        $quoted = DB::connection()->getPdo()->quote($table);
        $rows   = DB::select("PRAGMA table_info({$quoted})");
```

with:

```php
        $quoted = ConnectionManager::connection()->getPdo()->quote($table);
        $rows   = ConnectionManager::connection()->select("PRAGMA table_info({$quoted})");
```

In `getForeignKeys()`, replace:

```php
        $quoted = DB::connection()->getPdo()->quote($table);
        $rows   = DB::select("PRAGMA foreign_key_list({$quoted})");
```

with:

```php
        $quoted = ConnectionManager::connection()->getPdo()->quote($table);
        $rows   = ConnectionManager::connection()->select("PRAGMA foreign_key_list({$quoted})");
```

In `getIndexes()`, replace:

```php
        $quoted  = DB::connection()->getPdo()->quote($table);
        $indexes = DB::select("PRAGMA index_list({$quoted})");

        return collect($indexes)->map(function ($idx) {
            $info = DB::select("PRAGMA index_info({$idx->name})");
```

with:

```php
        $quoted  = ConnectionManager::connection()->getPdo()->quote($table);
        $indexes = ConnectionManager::connection()->select("PRAGMA index_list({$quoted})");

        return collect($indexes)->map(function ($idx) {
            $info = ConnectionManager::connection()->select("PRAGMA index_info({$idx->name})");
```

In `dropColumnSupported()`, replace:

```php
        $version = DB::select('SELECT sqlite_version() AS v')[0]->v ?? '0';
```

with:

```php
        $version = ConnectionManager::connection()->select('SELECT sqlite_version() AS v')[0]->v ?? '0';
```

- [ ] **Step 3: Run the driver's existing tests**

Run: `vendor/bin/pest tests/Unit/Drivers/SqliteDriverTest.php`
Expected: PASS (unchanged — these already exercise a live connection, so this is a real regression check, not just a syntax check).

- [ ] **Step 4: Write the failing "respects named connection" test**

Append to `tests/Unit/Drivers/SqliteDriverTest.php`:

```php
it('reads tables from config(dbmyadmin.connection) instead of the default connection', function () {
    config([
        'database.connections.dbmyadmin_secondary' => [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ],
    ]);

    Schema::connection('dbmyadmin_secondary')->create('only_in_secondary', function ($table) {
        $table->id();
    });

    config(['dbmyadmin.connection' => 'dbmyadmin_secondary']);
    \LucaPellegrino\DbMyAdmin\Support\ConnectionManager::reset();

    $driver = new SqliteDriver();
    $tables = $driver->getTables()->pluck('name');

    expect($tables)->toContain('only_in_secondary');
    expect($tables)->not->toContain('test_users');

    config(['dbmyadmin.connection' => null]);
    \LucaPellegrino\DbMyAdmin\Support\ConnectionManager::reset();
    Schema::connection('dbmyadmin_secondary')->dropIfExists('only_in_secondary');
    DB::purge('dbmyadmin_secondary');
});
```

- [ ] **Step 5: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Drivers/SqliteDriverTest.php --filter="respects named connection"`
Expected: FAIL before Step 2's edits are applied — but since Step 2 already ran, run this *before* committing to confirm it would have failed pre-refactor by temporarily reverting `src/Drivers/SqliteDriver.php` with `git stash push -- src/Drivers/SqliteDriver.php`, running the test (expect FAIL — table `only_in_secondary` not found, because `DB::select` ignores `config('dbmyadmin.connection')` and reads the default connection), then `git stash pop` to restore the refactored file.

- [ ] **Step 6: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Drivers/SqliteDriverTest.php`
Expected: PASS (7 tests — 6 existing + 1 new).

- [ ] **Step 7: Run the full suite**

Run: `vendor/bin/pest`
Expected: PASS (65 tests).

- [ ] **Step 8: Commit**

```bash
git add src/Drivers/SqliteDriver.php tests/Unit/Drivers/SqliteDriverTest.php
git commit -m "refactor: route SqliteDriver queries through ConnectionManager, prove named-connection respect"
```

---

### Task 7: Refactor `DatabaseTableResource.php` — rollback, FK toggling, truncate actions

**Files:**
- Modify: `src/Resources/DatabaseTableResource.php`

**Interfaces:**
- Consumes: `ConnectionManager::connection()` (Task 1)

**Note:** no new automated test for this task — `truncate`/`disableForeignKeyChecks`/`enableForeignKeyChecks` are destructive DDL/DML actions with no existing test coverage (confirmed: no test file currently exercises them), so there is nothing to extend without inventing new test infrastructure that's out of this refactor's scope. The existing 65-test suite staying green is the regression signal for this task; the end-to-end proof (Task 13) covers the read-path equivalent (listing tables) through the full stack.

- [ ] **Step 1: Update the `use` block**

Edit `src/Resources/DatabaseTableResource.php`. Replace:

```php
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
use LucaPellegrino\DbMyAdmin\DbMyAdminPlugin;
use LucaPellegrino\DbMyAdmin\Models\DatabaseTable;
use LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource\Pages;
```

with:

```php
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
use LucaPellegrino\DbMyAdmin\DbMyAdminPlugin;
use LucaPellegrino\DbMyAdmin\Models\DatabaseTable;
use LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource\Pages;
use LucaPellegrino\DbMyAdmin\Support\ConnectionManager;
```

- [ ] **Step 2: Update `rollbackFilamentTransaction`**

Replace:

```php
    protected static function rollbackFilamentTransaction(): void
    {
        try {
            DB::rollBack();
        } catch (\Throwable) {
        }
    }
```

with:

```php
    protected static function rollbackFilamentTransaction(): void
    {
        try {
            ConnectionManager::connection()->rollBack();
        } catch (\Throwable) {
        }
    }
```

- [ ] **Step 3: Update `disableForeignKeyChecks` and `enableForeignKeyChecks`**

Replace:

```php
    protected static function disableForeignKeyChecks(): void
    {
        match (DB::getDriverName()) {
            'mysql', 'mariadb' => DB::unprepared('SET FOREIGN_KEY_CHECKS=0'),
            'pgsql'            => DB::unprepared('SET session_replication_role = replica'),
            'sqlite'           => DB::unprepared('PRAGMA foreign_keys = OFF'),
            default            => null,
        };
    }

    protected static function enableForeignKeyChecks(): void
    {
        match (DB::getDriverName()) {
            'mysql', 'mariadb' => DB::unprepared('SET FOREIGN_KEY_CHECKS=1'),
            'pgsql'            => DB::unprepared('SET session_replication_role = DEFAULT'),
            'sqlite'           => DB::unprepared('PRAGMA foreign_keys = ON'),
            default            => null,
        };
    }
```

with:

```php
    protected static function disableForeignKeyChecks(): void
    {
        match (ConnectionManager::connection()->getDriverName()) {
            'mysql', 'mariadb' => ConnectionManager::connection()->unprepared('SET FOREIGN_KEY_CHECKS=0'),
            'pgsql'            => ConnectionManager::connection()->unprepared('SET session_replication_role = replica'),
            'sqlite'           => ConnectionManager::connection()->unprepared('PRAGMA foreign_keys = OFF'),
            default            => null,
        };
    }

    protected static function enableForeignKeyChecks(): void
    {
        match (ConnectionManager::connection()->getDriverName()) {
            'mysql', 'mariadb' => ConnectionManager::connection()->unprepared('SET FOREIGN_KEY_CHECKS=1'),
            'pgsql'            => ConnectionManager::connection()->unprepared('SET session_replication_role = DEFAULT'),
            'sqlite'           => ConnectionManager::connection()->unprepared('PRAGMA foreign_keys = ON'),
            default            => null,
        };
    }
```

- [ ] **Step 4: Update the four `truncate` call sites**

There are four occurrences of `DB::table(...)->truncate()` in this file:
1. Inside the `truncate` action's closure: `DB::table($tableName)->truncate();`
2. Inside the `truncate_with_fk` action's closure: `DB::table($tableName)->truncate();`
3. Inside the `truncate_selected` bulk action's `foreach`: `DB::table($record->name)->truncate();`
4. Inside the `truncate_selected_with_fk` bulk action's `foreach`: `DB::table($record->name)->truncate();`

Replace each occurrence of `DB::table(` with `ConnectionManager::connection()->table(` — the rest of each line (`$tableName)->truncate();` or `$record->name)->truncate();`) stays identical.

- [ ] **Step 5: Run the full suite**

Run: `vendor/bin/pest`
Expected: PASS (65 tests, no change in count).

- [ ] **Step 6: Commit**

```bash
git add src/Resources/DatabaseTableResource.php
git commit -m "refactor: route DatabaseTableResource DB calls through ConnectionManager"
```

---

### Task 8: Refactor `BrowseTableRecords.php`

**Files:**
- Modify: `src/Resources/DatabaseTableResource/Pages/BrowseTableRecords.php`

**Interfaces:**
- Consumes: `ConnectionManager::connection()`, `ConnectionManager::schema()` (Task 1)

No new test — `tableExists`, `resolveTableColumns`, `getFkOptions`, `performCreate`, `performUpdate`, `performDelete` have no existing dedicated unit tests (confirmed: `tests/` has no `BrowseTableRecordsTest.php`); the full suite staying green plus Task 13's end-to-end test cover the regression risk here.

- [ ] **Step 1: Update the `use` block**

Edit `src/Resources/DatabaseTableResource/Pages/BrowseTableRecords.php`. Replace:

```php
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
use LucaPellegrino\DbMyAdmin\Models\DynamicTableModel;
use LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource;
```

with:

```php
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
use LucaPellegrino\DbMyAdmin\Models\DynamicTableModel;
use LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource;
use LucaPellegrino\DbMyAdmin\Support\ConnectionManager;
```

- [ ] **Step 2: Update `tableExists` and `resolveTableColumns`**

Replace:

```php
    protected function tableExists(): bool
    {
        return Schema::hasTable($this->tableName);
    }

    /**
     * Returns columns using the driver.
     * Each element: ['name', 'type', 'nullable' (bool), 'default', 'key', 'extra', 'length']
     */
    protected function resolveTableColumns(): array
    {
        if (! Schema::hasTable($this->tableName)) {
            return [];
        }
```

with:

```php
    protected function tableExists(): bool
    {
        return ConnectionManager::schema()->hasTable($this->tableName);
    }

    /**
     * Returns columns using the driver.
     * Each element: ['name', 'type', 'nullable' (bool), 'default', 'key', 'extra', 'length']
     */
    protected function resolveTableColumns(): array
    {
        if (! ConnectionManager::schema()->hasTable($this->tableName)) {
            return [];
        }
```

- [ ] **Step 3: Update `getFkOptions`**

Replace:

```php
        $rows    = DB::table($relTable)->select($selectCols)->orderBy($labelColumns[0])->get();
```

with:

```php
        $rows    = ConnectionManager::connection()->table($relTable)->select($selectCols)->orderBy($labelColumns[0])->get();
```

- [ ] **Step 4: Update `performCreate`, `performUpdate`, `performDelete`**

Replace:

```php
            if (Schema::hasColumn($this->tableName, 'created_at') && ! isset($data['created_at'])) {
                $data['created_at'] = now();
            }
            if (Schema::hasColumn($this->tableName, 'updated_at') && ! isset($data['updated_at'])) {
                $data['updated_at'] = now();
            }

            DB::table($this->tableName)->insert($data);
```

with:

```php
            if (ConnectionManager::schema()->hasColumn($this->tableName, 'created_at') && ! isset($data['created_at'])) {
                $data['created_at'] = now();
            }
            if (ConnectionManager::schema()->hasColumn($this->tableName, 'updated_at') && ! isset($data['updated_at'])) {
                $data['updated_at'] = now();
            }

            ConnectionManager::connection()->table($this->tableName)->insert($data);
```

Replace:

```php
            if (Schema::hasColumn($this->tableName, 'updated_at')) {
                $data['updated_at'] = now();
            }

            DB::table($this->tableName)->where($pk, $original[$pk])->update($data);
```

with:

```php
            if (ConnectionManager::schema()->hasColumn($this->tableName, 'updated_at')) {
                $data['updated_at'] = now();
            }

            ConnectionManager::connection()->table($this->tableName)->where($pk, $original[$pk])->update($data);
```

Replace:

```php
            DB::table($this->tableName)->where($pk, $record[$pk])->delete();
```

with:

```php
            ConnectionManager::connection()->table($this->tableName)->where($pk, $record[$pk])->delete();
```

- [ ] **Step 5: Run the full suite**

Run: `vendor/bin/pest`
Expected: PASS (65 tests).

- [ ] **Step 6: Commit**

```bash
git add src/Resources/DatabaseTableResource/Pages/BrowseTableRecords.php
git commit -m "refactor: route BrowseTableRecords DB/Schema calls through ConnectionManager"
```

---

### Task 9: Refactor `CreateTable.php`

**Files:**
- Modify: `src/Resources/DatabaseTableResource/Pages/CreateTable.php`

**Interfaces:**
- Consumes: `ConnectionManager::connection()` (Task 1)

- [ ] **Step 1: Update the `use` block**

Edit `src/Resources/DatabaseTableResource/Pages/CreateTable.php`. Replace:

```php
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
use LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource;
```

with:

```php
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Log;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
use LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource;
use LucaPellegrino\DbMyAdmin\Support\ConnectionManager;
```

- [ ] **Step 2: Update the DDL execution call**

Replace:

```php
        try {
            DB::unprepared($this->generatedDdl);

            Log::info('Tabella creata', [
```

with:

```php
        try {
            ConnectionManager::connection()->unprepared($this->generatedDdl);

            Log::info('Tabella creata', [
```

- [ ] **Step 3: Run the full suite**

Run: `vendor/bin/pest`
Expected: PASS (65 tests).

- [ ] **Step 4: Commit**

```bash
git add src/Resources/DatabaseTableResource/Pages/CreateTable.php
git commit -m "refactor: route CreateTable DDL execution through ConnectionManager"
```

---

### Task 10: Refactor `AlterTable.php`

**Files:**
- Modify: `src/Resources/DatabaseTableResource/Pages/AlterTable.php`

**Interfaces:**
- Consumes: `ConnectionManager::connection()`, `ConnectionManager::schema()` (Task 1)

- [ ] **Step 1: Update the `use` block**

Edit `src/Resources/DatabaseTableResource/Pages/AlterTable.php`. Replace:

```php
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
use LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource;
```

with:

```php
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Log;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
use LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource;
use LucaPellegrino\DbMyAdmin\Support\ConnectionManager;
```

- [ ] **Step 2: Update `mount()`**

Replace:

```php
    public function mount(string $record): void
    {
        $this->tableName = $record;

        abort_unless(Schema::hasTable($this->tableName), 404);

        $this->loadExistingColumns();
    }
```

with:

```php
    public function mount(string $record): void
    {
        $this->tableName = $record;

        abort_unless(ConnectionManager::schema()->hasTable($this->tableName), 404);

        $this->loadExistingColumns();
    }
```

- [ ] **Step 3: Update the DDL execution call**

Replace:

```php
        try {
            DB::unprepared($this->generatedDdl);

            Log::info('Tabella modificata', [
```

with:

```php
        try {
            ConnectionManager::connection()->unprepared($this->generatedDdl);

            Log::info('Tabella modificata', [
```

- [ ] **Step 4: Run the full suite**

Run: `vendor/bin/pest`
Expected: PASS (65 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Resources/DatabaseTableResource/Pages/AlterTable.php
git commit -m "refactor: route AlterTable DDL execution and table check through ConnectionManager"
```

---

### Task 11: Refactor `SqlQueryRunner.php`

**Files:**
- Modify: `src/Resources/DatabaseTableResource/Pages/SqlQueryRunner.php`

**Interfaces:**
- Consumes: `ConnectionManager::connection()` (Task 1)

**Note:** `SavedQuery::class` (imported and used elsewhere in this file for the save/load/delete saved-query features) is explicitly **not** touched — see Global Constraints. Only the two raw SQL execution call sites change.

- [ ] **Step 1: Update the `use` block**

Edit `src/Resources/DatabaseTableResource/Pages/SqlQueryRunner.php`. Replace:

```php
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
use LucaPellegrino\DbMyAdmin\Models\SavedQuery;
use LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource;
```

with:

```php
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Log;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
use LucaPellegrino\DbMyAdmin\Models\SavedQuery;
use LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource;
use LucaPellegrino\DbMyAdmin\Support\ConnectionManager;
```

- [ ] **Step 2: Update the two query execution call sites**

Replace:

```php
                $rows = DB::select($sql);
```

with:

```php
                $rows = ConnectionManager::connection()->select($sql);
```

Replace:

```php
                $affected = DB::affectingStatement($sql);
```

with:

```php
                $affected = ConnectionManager::connection()->affectingStatement($sql);
```

- [ ] **Step 3: Run the full suite**

Run: `vendor/bin/pest`
Expected: PASS (65 tests — including all of `Tests\Feature\SqlQueryRunnerTest`, which exercises these exact two call sites live).

- [ ] **Step 4: Commit**

```bash
git add src/Resources/DatabaseTableResource/Pages/SqlQueryRunner.php
git commit -m "refactor: route SqlQueryRunner execution through ConnectionManager"
```

---

### Task 12: Render hook + active-connection label helper (Pro UI injection points)

**Files:**
- Modify: `resources/views/pages/list-database-tables.blade.php`
- Modify: `src/Resources/DatabaseTableResource.php`
- Create: `src/Contracts/ActiveConnectionLabelProvider.php`
- Create: `src/Support/NullConnectionLabelProvider.php`
- Test: `tests/Unit/DbMyAdminServiceProviderTest.php` (extend)

**Interfaces:**
- Produces: `LucaPellegrino\DbMyAdmin\Contracts\ActiveConnectionLabelProvider::label(): ?string` — Pro will bind an implementation that returns e.g. `"Produzione (MySQL @ db.example.com)"` when a connection is active, `null` otherwise.
- Produces: Filament render hook `dbmyadmin::connection-banner`, emitted at the top of the table list view (no-op — renders nothing — unless something is registered on it).
- Produces: the TRUNCATE/"Svuota Tabella" and "Svuota (disabilita FK)" confirmation modal descriptions append the active connection's label when `ActiveConnectionLabelProvider::label()` returns non-null.

- [ ] **Step 1: Read the current view file**

Read `resources/views/pages/list-database-tables.blade.php` to find its opening `<x-filament-panels::page>` (or equivalent) tag before editing — the render hook goes immediately inside it, before the table component.

- [ ] **Step 2: Create the label provider contract and default**

Create `src/Contracts/ActiveConnectionLabelProvider.php`:

```php
<?php

namespace LucaPellegrino\DbMyAdmin\Contracts;

interface ActiveConnectionLabelProvider
{
    /**
     * A short human-readable label for the currently active connection
     * (e.g. "Produzione (MySQL @ db.example.com)"), or null when the app's
     * default connection is in use (the only state possible without an
     * extension installed).
     */
    public function label(): ?string;
}
```

Create `src/Support/NullConnectionLabelProvider.php`:

```php
<?php

namespace LucaPellegrino\DbMyAdmin\Support;

use LucaPellegrino\DbMyAdmin\Contracts\ActiveConnectionLabelProvider;

class NullConnectionLabelProvider implements ActiveConnectionLabelProvider
{
    public function label(): ?string
    {
        return null;
    }
}
```

- [ ] **Step 3: Bind the default label provider**

Edit `src/DbMyAdminServiceProvider.php`. Add the import:

```php
use LucaPellegrino\DbMyAdmin\Contracts\ActiveConnectionLabelProvider;
```

(alongside the existing `ActiveConnectionResolver` import), and in `register()`, right after the existing `$this->app->bind(ActiveConnectionResolver::class, NullConnectionResolver::class);` line, add:

```php
        $this->app->bind(ActiveConnectionLabelProvider::class, \LucaPellegrino\DbMyAdmin\Support\NullConnectionLabelProvider::class);
```

- [ ] **Step 4: Add a test for the new binding**

Append to `tests/Unit/DbMyAdminServiceProviderTest.php`:

```php
it('binds NullConnectionLabelProvider as the default ActiveConnectionLabelProvider', function () {
    expect(app(\LucaPellegrino\DbMyAdmin\Contracts\ActiveConnectionLabelProvider::class))
        ->toBeInstanceOf(\LucaPellegrino\DbMyAdmin\Support\NullConnectionLabelProvider::class);

    expect(app(\LucaPellegrino\DbMyAdmin\Contracts\ActiveConnectionLabelProvider::class)->label())->toBeNull();
});
```

- [ ] **Step 5: Run the test**

Run: `vendor/bin/pest tests/Unit/DbMyAdminServiceProviderTest.php`
Expected: PASS (4 tests).

- [ ] **Step 6: Add the render hook to the table list view**

Edit `resources/views/pages/list-database-tables.blade.php`. Add this line immediately after the view's opening wrapper tag (whatever it is — typically `<x-filament-panels::page>`), before any other content:

```blade
{{ \Filament\Support\Facades\FilamentView::renderHook('dbmyadmin::connection-banner') }}
```

This renders nothing when no listener is registered (Filament's `renderHook()` returns an empty `HtmlString` in that case) — verify this by loading the page in a browser or running the existing test suite (Task-agnostic pages aren't rendered in Pest here, so this is a visual/manual check, not an automated one).

- [ ] **Step 7: Wire the label into the TRUNCATE confirmation modals**

Edit `src/Resources/DatabaseTableResource.php`. Replace:

```php
                    ->modalDescription(fn ($record) => "Sei sicuro di voler svuotare la tabella '{$record->name}'? Questa operazione eliminerà tutti i dati in modo permanente.")
```

(inside the `truncate` action) with:

```php
                    ->modalDescription(function ($record) {
                        $label = app(\LucaPellegrino\DbMyAdmin\Contracts\ActiveConnectionLabelProvider::class)->label();
                        $suffix = $label ? " sulla connessione '{$label}'" : '';

                        return "Sei sicuro di voler svuotare la tabella '{$record->name}'{$suffix}? Questa operazione eliminerà tutti i dati in modo permanente.";
                    })
```

Apply the identical transformation to the `truncate_with_fk` action's `modalDescription`, which currently reads:

```php
                    ->modalDescription(fn ($record) => "Sei sicuro di voler svuotare la tabella '{$record->name}' disabilitando temporaneamente i controlli sulle chiavi esterne? Questa operazione eliminerà tutti i dati in modo permanente.")
```

Replace with:

```php
                    ->modalDescription(function ($record) {
                        $label = app(\LucaPellegrino\DbMyAdmin\Contracts\ActiveConnectionLabelProvider::class)->label();
                        $suffix = $label ? " sulla connessione '{$label}'" : '';

                        return "Sei sicuro di voler svuotare la tabella '{$record->name}'{$suffix} disabilitando temporaneamente i controlli sulle chiavi esterne? Questa operazione eliminerà tutti i dati in modo permanente.";
                    })
```

- [ ] **Step 8: Run the full suite**

Run: `vendor/bin/pest`
Expected: PASS (66 tests — 65 + 1 new).

- [ ] **Step 9: Commit**

```bash
git add resources/views/pages/list-database-tables.blade.php src/Resources/DatabaseTableResource.php src/DbMyAdminServiceProvider.php src/Contracts/ActiveConnectionLabelProvider.php src/Support/NullConnectionLabelProvider.php tests/Unit/DbMyAdminServiceProviderTest.php
git commit -m "feat: add render hook and ActiveConnectionLabelProvider for Pro UI injection"
```

---

### Task 13: End-to-end integration test — named connection through the full stack

**Files:**
- Test: `tests/Feature/NamedConnectionIntegrationTest.php` (new)

**Interfaces:**
- Consumes: everything from Tasks 1–11 (`ActiveConnectionResolver`, `ConnectionManager`, `DatabaseDriver`, `DatabaseTable`, `ListDatabaseTables`)

This is the single most important test in this plan: it proves that binding an `ActiveConnectionResolver` — exactly the extension point Pro will use — actually redirects the whole table-list feature to a different database, with zero Core code changes beyond what this plan already did.

- [ ] **Step 1: Write the test**

SQLite `:memory:` connections are private per PDO handle, so the resolver can't just return a fresh `:memory:` config — that would produce an empty database, not the populated one from `beforeEach()`. Use a temporary **file-backed** SQLite database instead, which both the resolver's returned config and the directly-opened `dbmyadmin_e2e_secondary` connection can share.

Create `tests/Feature/NamedConnectionIntegrationTest.php`:

```php
<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LucaPellegrino\DbMyAdmin\Contracts\ActiveConnectionResolver;
use LucaPellegrino\DbMyAdmin\Models\DatabaseTable;
use LucaPellegrino\DbMyAdmin\Support\ConnectionManager;

beforeEach(function () {
    $this->secondaryDbPath = sys_get_temp_dir() . '/dbmyadmin_e2e_' . uniqid() . '.sqlite';
    touch($this->secondaryDbPath);

    config([
        'database.connections.dbmyadmin_e2e_secondary' => [
            'driver'   => 'sqlite',
            'database' => $this->secondaryDbPath,
            'prefix'   => '',
        ],
    ]);

    Schema::connection('dbmyadmin_e2e_secondary')->create('secondary_only_table', function ($table) {
        $table->id();
    });
});

afterEach(function () {
    ConnectionManager::reset();
    DatabaseTable::clearCache();
    config(['dbmyadmin.connection' => null]);
    DB::purge('dbmyadmin_e2e_secondary');
    @unlink($this->secondaryDbPath);
});

it('lists tables from the connection an ActiveConnectionResolver activates, not the app default', function () {
    app()->bind(ActiveConnectionResolver::class, function () {
        return new class ($this) implements ActiveConnectionResolver {
            public function __construct(private $test) {}

            public function resolve(): ?array
            {
                return [
                    'driver'   => 'sqlite',
                    'database' => $this->test->secondaryDbPath,
                    'prefix'   => '',
                ];
            }
        };
    });

    $names = DatabaseTable::getAllModels()->pluck('name');

    expect(config('dbmyadmin.connection'))->toBe('dbmyadmin_active');
    expect($names)->toContain('secondary_only_table');
    expect($names)->not->toContain('test_users');
});

it('falls back to the app default connection when the resolver returns null', function () {
    $names = DatabaseTable::getAllModels()->pluck('name');

    expect(config('dbmyadmin.connection'))->toBeNull();
    expect($names)->not->toContain('secondary_only_table');
});
```

- [ ] **Step 2: Run the test**

Run: `vendor/bin/pest tests/Feature/NamedConnectionIntegrationTest.php`
Expected: PASS (2 tests). If the first test fails with `secondary_only_table` missing, double-check `ConnectionManager::activate()` (Task 1, Step 6) is being reached — add a `dd(config('dbmyadmin.connection'))` right before the `expect()` calls to debug, then remove it once green.

- [ ] **Step 3: Run the full suite**

Run: `vendor/bin/pest`
Expected: PASS (68 tests — 66 + 2 new).

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/NamedConnectionIntegrationTest.php
git commit -m "test: prove ActiveConnectionResolver redirects the table list end-to-end"
```

---

### Task 14: Documentation, version bump, release

**Files:**
- Modify: `README.md`
- Modify: `docs/publishing.md` (only if the version-bump checklist needs updating — read it first, likely no changes needed)

**Interfaces:** none — this task is documentation and release mechanics only.

- [ ] **Step 1: Document the new config keys in the README**

Edit `README.md`. In the `## Configuration` section, immediately after the closing ` ``` ` of the main config code block (i.e. right before the `### Excluding additional tables` heading), add:

```markdown
### Extensibility (`drivers` and `connection`)

Two config keys exist purely as extension points for third-party add-ons and aren't meant to be edited by hand in a normal installation:

- `drivers` maps a driver name (as reported by the active connection, or set explicitly via `driver`) to the `DatabaseDriver` class that handles it. An extension package can merge additional entries into this array from its own service provider (e.g. to add SQL Server support) without forking this package.
- `connection` is the name of the Laravel database connection DbMyAdmin currently targets. It's `null` by default, meaning "use this application's default connection" — the only mode this package supports on its own. It's managed at runtime by `LucaPellegrino\DbMyAdmin\Support\ConnectionManager` and should not be set directly in config files.
```

- [ ] **Step 2: Read `docs/publishing.md` to confirm the release checklist still applies**

Read the file. If it references the driver `match` statement or anything else changed by this plan by name, update it; otherwise leave it untouched (it's a generic release checklist, most likely nothing to change).

- [ ] **Step 3: Run the full suite one last time**

Run: `vendor/bin/pest`
Expected: PASS (68 tests).

- [ ] **Step 4: Commit the docs**

```bash
git add README.md
git commit -m "docs: document the drivers/connection extension-point config keys"
```

- [ ] **Step 5: Confirm with the user before tagging/pushing**

This step is a hard stop, not an automatic action: ask the user to confirm the version number (recommend `v1.1.0` — a minor bump, since this adds new public extension-point API surface even though behavior is unchanged for existing users) and confirm they want to push, following the same pattern used earlier in this project (every previous release in this repo's history was confirmed with the user before `git push`/`git tag` ran). Do not run Step 6 without that confirmation.

- [ ] **Step 6: Tag and push (only after user confirmation)**

```bash
git tag -a v1.1.0 -m "v1.1.0: connection extension points (ActiveConnectionResolver, config-driven driver map, render hooks) — no behavior change without an extension installed"
git push origin main
git push origin v1.1.0
```

---

## Post-Plan State

After Task 14, `lucapellegrino/dbmyadmin` v1.1.0 behaves identically to v1.0.11 for every existing user, but exposes:
- `LucaPellegrino\DbMyAdmin\Contracts\ActiveConnectionResolver` (bind your own to redirect DbMyAdmin at a different connection per request)
- `LucaPellegrino\DbMyAdmin\Contracts\ActiveConnectionLabelProvider` (bind your own to surface a connection name in the UI/confirmation modals)
- `config('dbmyadmin.drivers')` (merge in additional driver mappings)
- Filament render hook `dbmyadmin::connection-banner`

This is the complete prerequisite surface for Plan 2 (`dbmyadmin-pro` repo bootstrap + Connections CRUD resource + `SessionConnectionResolver`), which should be written as its own plan once this one has landed and been tagged, per the design spec's §9 maintenance model.

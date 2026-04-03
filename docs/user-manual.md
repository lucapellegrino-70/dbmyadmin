# DbMyAdmin — User Manual

**Package:** `lucapellegrino/dbmyadmin`
**Version:** 1.x
**Stack:** Laravel 12, Filament 5, PHP 8.3+

---

## Introduction

DbMyAdmin is a database management interface integrated directly into your Filament admin panel. It provides functionality similar to phpMyAdmin: browse tables and records, execute SQL queries, save and manage your queries, and modify table structure — all without leaving your application.

Supported databases:
- MySQL / MariaDB
- PostgreSQL
- SQLite

---

## Installation

### 1. Install via Composer

```bash
composer require lucapellegrino/dbmyadmin
```

### 2. Publish and run migrations

```bash
php artisan vendor:publish --tag=dbmyadmin-migrations
php artisan migrate
```

This creates the `dbmyadmin_saved_queries` table used to persist your saved SQL queries.

### 3. Register the plugin in your Panel Provider

Open `app/Providers/Filament/AdminPanelProvider.php` and add the plugin:

```php
use LucaPellegrino\DbMyAdmin\DbMyAdminPlugin;

->plugins([
    DbMyAdminPlugin::make(),
])
```

### 4. (Optional) Publish the config

```bash
php artisan vendor:publish --tag=dbmyadmin-config
```

This creates `config/dbmyadmin.php` where you can customize the plugin behavior.

---

## Configuration

All options live in `config/dbmyadmin.php`:

```php
return [
    // Database driver: auto-detected from your DB connection, or set manually
    // Accepted values: 'auto', 'mysql', 'pgsql', 'sqlite'
    'driver' => 'auto',

    // Tables hidden from the table list
    'excluded_tables' => [
        'migrations',
        'dbmyadmin_saved_queries',
        'sessions',
        'cache',
        'jobs',
        'failed_jobs',
        'password_reset_tokens',
        'personal_access_tokens',
    ],

    // SQL Runner settings
    'query_runner' => [
        // SQL statement types blocked in the SQL Runner for safety
        // Add or remove entries to customize which statements are blocked
        'blocked_statements' => [
            'DROP', 'TRUNCATE', 'ALTER', 'CREATE',
            'RENAME', 'GRANT', 'REVOKE', 'LOCK', 'UNLOCK',
        ],

        // Maximum rows returned per query execution
        'max_rows' => 1000,
    ],

    // Name of the table used to store saved queries
    'saved_queries_table' => 'dbmyadmin_saved_queries',

    // Whether to log all operations (CREATE, ALTER, query execution, etc.)
    'logging' => true,
];
```

### Excluding additional tables

Add table names to `excluded_tables` to hide them from the interface:

```php
'excluded_tables' => [
    'migrations',
    'dbmyadmin_saved_queries',
    'my_sensitive_table',
    'internal_logs',
],
```

### Customizing blocked statements

By default, the SQL Runner blocks dangerous statements. You can customize this list:

```php
// Allow TRUNCATE (not recommended in production)
'blocked_statements' => [
    'DROP', 'ALTER', 'CREATE', 'RENAME',
    'GRANT', 'REVOKE', 'LOCK', 'UNLOCK',
],

// Block everything except SELECT
'blocked_statements' => [
    'DROP', 'TRUNCATE', 'ALTER', 'CREATE', 'RENAME',
    'GRANT', 'REVOKE', 'LOCK', 'UNLOCK',
    'INSERT', 'UPDATE', 'DELETE',
],
```

---

## Authorization

### Default behavior

By default, access to DbMyAdmin is controlled by whoever has access to your Filament panel. No extra configuration is needed.

### Filament Shield integration

DbMyAdmin is fully compatible with [Filament Shield](https://github.com/bezhanSalleh/filament-shield). Once Shield is installed, it automatically generates the following permissions for the plugin:

| Permission                     | Controls                          |
|--------------------------------|-----------------------------------|
| `view_any_database_table`      | Access the table list             |
| `create_database_table`        | Create new tables                 |
| `update_database_table`        | Alter existing tables             |
| `delete_database_table`        | Truncate / drop tables            |

Assign these permissions to roles via Shield's interface as you would for any other resource.

### Custom authorization gate

You can restrict the entire plugin to specific users via a closure:

```php
DbMyAdminPlugin::make()
    ->authorize(fn() => auth()->user()->hasRole('superadmin'))
```

This gate is evaluated before Shield permissions. If it returns `false`, the user cannot access any part of the plugin regardless of their Shield permissions.

### Navigation customization

```php
DbMyAdminPlugin::make()
    ->navigationGroup('Administration')
    ->navigationIcon('heroicon-o-circle-stack')
```

---

## Features

### Table List

The main page shows all tables in your database with:
- **Row count** — approximate number of records
- **Data size** — space used by data
- **Index size** — space used by indexes
- **Total size** — combined size
- **Engine** — storage engine (MySQL only)
- **Collation** — character set collation

Available actions per table:
- **Browse** — open the record browser for that table
- **Structure** — view column definitions in a modal
- **Truncate** — delete all records (with confirmation)
- **Truncate (disable FK)** — truncate with foreign key checks disabled

Bulk actions:
- **Truncate selected** — truncate multiple tables at once
- **Truncate selected (disable FK)** — same with FK checks disabled

Header actions:
- **Create Table** — open the table creation wizard
- **SQL Runner** — open the SQL query editor

> Tables listed in `excluded_tables` in the config are not shown.

---

### Browse Table Records

Browse, search, create, edit, and delete records in any table.

- Columns are detected automatically from the table structure
- Foreign key columns show a dropdown with values from the related table, auto-detecting a suitable label column
- Supports all common column types: strings, integers, decimals, booleans, dates, datetimes, JSON, text
- Actions per row: **Edit**, **Delete**
- Header action: **Create** new record

> Note: the interface adapts to the actual columns of each table — no configuration required.

---

### SQL Runner

A full-featured SQL editor with:

**Editor:**
- Syntax-aware text area with autocomplete
- Autocomplete suggests table names, column names, and SQL keywords as you type
- Templates for common queries (SELECT, INSERT, UPDATE, DELETE, etc.)
- Clear button to reset the editor

**Execution:**
- Run button executes the query against your database
- Results displayed in a paginated table with sticky headers
- Shows total rows, execution time, and affected rows
- Blocked statements (configurable) are rejected before execution

**Schema Browser (sidebar):**
- Expandable list of all tables and their columns
- Click a table or column name to insert it into the editor

**Saved Queries:**
- Save any query with a name and optional description
- Search through saved queries
- Load a saved query back into the editor with one click
- Edit the name/description of a saved query
- Delete saved queries

> The SQL Runner only executes queries — it does not modify table structure. Use the Create Table or Alter Table pages for DDL operations.

---

### Create Table

A visual wizard for creating new database tables.

**Table options:**
- Table name
- Comment / description
- Storage engine (MySQL)
- Character set and collation (MySQL)

**Columns:**
- Add/remove columns dynamically
- For each column: name, type, length/precision, default value, nullable, unsigned, auto-increment
- Move columns up/down to change order
- Column types available: INT, BIGINT, TINYINT, SMALLINT, MEDIUMINT, FLOAT, DOUBLE, DECIMAL, VARCHAR, CHAR, TEXT, MEDIUMTEXT, LONGTEXT, DATE, DATETIME, TIMESTAMP, TIME, BOOLEAN, JSON, BLOB, ENUM, SET

**Foreign Keys:**
- Add foreign key constraints between tables
- Select the referenced table and column
- Choose ON DELETE and ON UPDATE actions: RESTRICT, CASCADE, SET NULL, NO ACTION

**DDL Preview:**
- Live-generated CREATE TABLE statement
- Inspect the exact SQL before executing

---

### Alter Table

Modify the structure of an existing table.

**Modify existing columns:**
- Change column name, type, length, default, nullable, unsigned
- Mark columns for dropping
- Changes are highlighted visually before applying

**Add new columns:**
- Add one or more new columns
- Specify position (AFTER which existing column)

**DDL Preview:**
- Live-generated ALTER TABLE statement

**Apply:**
- Executes the generated DDL against the database
- Operation is logged

> SQLite has limited ALTER TABLE support. Depending on the SQLite version, some operations (e.g. DROP COLUMN) may be unavailable. Unsupported operations are hidden automatically.

---

## Logging

When `logging` is enabled in config (default: `true`), all operations are written to your Laravel log:

- Table creation
- Table alteration
- Table truncation
- SQL query execution
- Record create/update/delete

Each log entry includes the authenticated user's email and the operation performed.

---

## Upgrading

### From Filament 3.3 (app-level code)

If you were using an older version of this code directly in your app (before it became a package), follow these steps:

1. Remove the old files from your app:
   - `app/Models/DatabaseTable.php`
   - `app/Models/SavedQuery.php`
   - `app/Filament/Resources/DatabaseTableResource.php`
   - `app/Filament/Resources/DatabaseTableResource/Pages/*`
   - Blade views in `resources/views/filament/pages/`
   - The old migration for `saved_queries`

2. Install the package: `composer require lucapellegrino/dbmyadmin`

3. Publish and run migrations (the table is renamed to `dbmyadmin_saved_queries` — migrate your existing data manually if needed)

4. Register the plugin in your Panel Provider

---

## Troubleshooting

**The plugin does not appear in the navigation**
- Ensure the plugin is registered in your Panel Provider
- Check that the authenticated user has the `view_any_database_table` Shield permission (if Shield is installed)
- Check that your `->authorize()` closure returns `true` for the current user

**Tables are not showing up**
- Check the `excluded_tables` config — the table may be in the exclusion list
- Ensure the database user has SELECT permissions on `information_schema`

**SQL Runner blocks my query**
- Check `blocked_statements` in config and remove the statement type you need
- Note that DDL operations (CREATE TABLE, ALTER TABLE) have dedicated pages with safer interfaces

**SQLite: Alter Table options are missing**
- SQLite has limited ALTER TABLE support — unsupported operations are hidden automatically
- For complex schema changes on SQLite, consider using Laravel migrations

**Foreign keys not detected in Browse Records**
- Foreign key detection requires the database user to have access to `information_schema.KEY_COLUMN_USAGE`
- On SQLite, foreign key detection uses `PRAGMA foreign_key_list`

---

## License

MIT License. See `LICENSE` file in the package root.

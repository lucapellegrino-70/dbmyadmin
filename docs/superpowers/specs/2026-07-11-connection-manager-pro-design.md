# DbMyAdmin Pro — Multi-Connection Manager — Design Document

**Date:** 2026-07-11
**Packages:** `lucapellegrino/dbmyadmin` (Core, public/MIT), `lucapellegrino/dbmyadmin-pro` (Pro, private/commercial)
**Stack:** Laravel 12/13, Filament 5, Livewire 4, PHP 8.3+

---

## Overview

Today DbMyAdmin only ever operates against the host Laravel application's own default database connection. This feature adds the ability to define, store, and switch between multiple external database connections (MySQL, PostgreSQL, SQL Server), including connections tunneled over SSH (password or private-key auth).

This is a **commercial feature**, sold as a separate paid, closed-source package (`dbmyadmin-pro`) that depends on the existing free/MIT Core. The free Core is unaffected in behavior for users who don't install Pro; it gains a small set of extension points so Pro can hook in without forking it.

---

## 1. Two-Package Architecture

```
lukemyadmin/            (this repo — public, MIT, unchanged license)
  composer: lucapellegrino/dbmyadmin

lukemyadmin-pro/         (new sibling repo — private, commercial, one-time purchase)
  composer: lucapellegrino/dbmyadmin-pro
  "require": { "lucapellegrino/dbmyadmin": "^1.x" }
```

Existing Core pages (List/Browse/Alter/Create Table, SQL Query Runner) are **not duplicated**. Pro only changes *which* database connection those pages operate against. This keeps the coupling surface small and means most future bug fixes/features land in exactly one repo (see §9, Maintenance Model).

### Distribution & licensing

- `dbmyadmin-pro` is a private GitHub repository. Access is granted **per customer via a dedicated read-only SSH Deploy Key** (one keypair per sale, public half added as a Deploy Key on the repo, private half sent to the customer). No expiration, no GitHub account linkage required for the customer, revocation = deleting that one deploy key.
- No runtime license-key validation. Repo access *is* the license. This matches the one-time-purchase model (no subscription to manage).
- Sale/delivery mechanics (payment platform, generating/sending the keypair) are an operational runbook, not application code — out of scope for this spec.

---

## 2. Core Changes (free package, backward-compatible)

These are the only changes required in `lucapellegrino/dbmyadmin`. None alter behavior for users without Pro installed.

### 2.1 Config-driven driver map

Replace the hardcoded `match` in `DbMyAdminServiceProvider::register()` with a config-driven map:

```php
// config/dbmyadmin.php
'drivers' => [
    'mysql'   => \LucaPellegrino\DbMyAdmin\Drivers\MySqlDriver::class,
    'mariadb' => \LucaPellegrino\DbMyAdmin\Drivers\MySqlDriver::class,
    'pgsql'   => \LucaPellegrino\DbMyAdmin\Drivers\PostgresDriver::class,
    'sqlite'  => \LucaPellegrino\DbMyAdmin\Drivers\SqliteDriver::class,
],
```

Pro's service provider merges in `'sqlsrv' => SqlServerDriver::class` without touching Core source.

### 2.2 Named, isolated connection

Introduce `config('dbmyadmin.connection')` (default `null`). `null` means "use the app's default connection" — today's behavior, unchanged.

All places that currently assume the default connection route through this config instead:
- `DatabaseTable::$connection` (currently hardcoded `null`) resolves dynamically from `config('dbmyadmin.connection')`.
- Raw `DB::table(...)` / `DB::unprepared(...)` calls in `DatabaseTableResource` (truncate, FK toggling) and in the driver implementations use `DB::connection(config('dbmyadmin.connection'))` instead of the bare `DB` facade default.

`database.default` is **never** mutated. This was an explicit decision: this package runs destructive actions (TRUNCATE, DROP COLUMN, ALTER), so there must be zero chance of a request-scoped mixup bleeding into the host app's real connection.

### 2.3 `ActiveConnectionResolver` interface

```php
namespace LucaPellegrino\DbMyAdmin\Contracts;

interface ActiveConnectionResolver
{
    /**
     * Return a Laravel database connection config array to activate for
     * the current request, or null to use the app's default connection.
     */
    public function resolve(): ?array;
}
```

Core binds a default no-op implementation (`NullConnectionResolver`, always returns `null`). Pro's service provider rebinds this interface to its own resolver (see §5).

Core resolves this once per request (first time `DatabaseDriver` or `config('dbmyadmin.connection')` is needed): if the resolver returns an array, Core registers it as `database.connections.dbmyadmin_active`, purges/reconnects, and sets `config(['dbmyadmin.connection' => 'dbmyadmin_active'])` for the rest of the request.

### 2.4 Render hooks for Pro UI injection

Core's Blade views for the table list, browse, and confirmation modals emit named Filament render hooks (no-op when nothing is registered):
- `dbmyadmin::connection-banner` — top of List/Browse/Alter/Create pages.
- A small optional helper (e.g. `dbmyadmin_active_connection_label(): ?string`, backed by a container binding Core defaults to returning `null`) that the TRUNCATE/DROP confirmation modal text interpolates into its existing description when non-null.

Pro listens on the render hook to inject the active-connection banner and overrides the label binding to feed the modal text. Zero Blade/view forking required.

---

## 3. Pro Package — Data Model

Migration `create_dbmyadmin_connections_table` (publishable, same pattern as the existing `dbmyadmin_saved_queries` migration):

| Field | Notes |
|---|---|
| `id` | |
| `user_id` | FK; connections are private per user (not shared across the panel) |
| `name` | user-facing label |
| `type` | `mysql` \| `pgsql` \| `sqlsrv` |
| `host`, `port` | port pre-filled by type default (3306 / 5432 / 1433), editable |
| `database` | target database/schema name |
| `username`, `password` | `password` cast `encrypted` |
| `ssh_enabled` | bool |
| `ssh_host`, `ssh_port` (default 22), `ssh_username` | |
| `ssh_auth_method` | `password` \| `private_key` |
| `ssh_password` | cast `encrypted`, nullable |
| `ssh_private_key` | cast `encrypted`, nullable (PEM content) |
| `ssh_passphrase` | cast `encrypted`, nullable |
| `last_used_at` | nullable, informational |
| timestamps | |

`DatabaseConnection` model, scoped by default to `auth()->id()` (global scope, mirroring the "private per user" decision).

---

## 4. Pro Package — Connection Lifecycle & UX

New Filament resource **"Connessioni Database"**:

- **Form**: conditional fields — SSH fields only visible when `ssh_enabled`; `ssh_password` vs `ssh_private_key`/`ssh_passphrase` visible based on `ssh_auth_method`. Port auto-fills a sensible default when `type` changes.
- **Test connessione** action (row + form): attempts a real DB connection (establishing the SSH tunnel first if enabled) without persisting any session state. Surfaces clear, specific errors — including missing prerequisites (§6, §7) — rather than raw PDO/process exceptions.
- **Connetti** action (row): sets `session(['dbmyadmin_active_connection_id' => $id])`, redirects to the existing `DatabaseTableResource` index (now operating against this connection via the resolver, §5).
- **Torna al DB locale** action (shown in the banner, §2.4, whenever a connection is active): clears the session key.
- If the currently-active connection record is deleted, the resolver simply finds nothing for that id on the next request and falls back to the app's default connection — no dangling state.

Active connection selection is **session-scoped**, not persisted per-user across logins — a fresh login always starts on the app's local DB, which is the safer default.

---

## 5. Pro Package — `ActiveConnectionResolver` Implementation

```php
class SessionConnectionResolver implements ActiveConnectionResolver
{
    public function resolve(): ?array
    {
        $id = session('dbmyadmin_active_connection_id');
        if (! $id) return null;

        $connection = DatabaseConnection::find($id); // already user-scoped
        if (! $connection) return null;

        $host = $connection->host;
        $port = $connection->port;

        if ($connection->ssh_enabled) {
            [$host, $port] = app(TunnelManager::class)->ensureTunnel($connection);
        }

        return [
            'driver'   => $connection->type,
            'host'     => $host,
            'port'     => $port,
            'database' => $connection->database,
            'username' => $connection->username,
            'password' => $connection->password,
        ];
    }
}
```

Registered in Pro's service provider: `$this->app->bind(ActiveConnectionResolver::class, SessionConnectionResolver::class);`.

---

## 6. Pro Package — SSH Tunnel Subsystem

- **Mechanism**: shells out to the system `ssh` binary via `Symfony\Component\Process\Process` — `ssh -f -N -o StrictHostKeyChecking=no -o ExitOnForwardFailure=yes -L {localPort}:{dbHost}:{dbPort} {sshUser}@{sshHost} -p {sshPort} [-i {tmpKeyFile}]`. This mirrors what desktop DB GUI tools do; a pure-PHP SSH local-forward implementation (phpseclib) was rejected as significantly more complex and fragile for no real portability gain (see brainstorming discussion).
- **Prerequisite check**: before allowing a connect/test on an SSH-enabled connection, verify `ssh` is resolvable on `PATH` and `proc_open`/`exec` are not disabled (`disable_functions`). Password-auth additionally requires `sshpass`. Missing prerequisites block the action with a specific, actionable message (never a raw process/PDO exception) — the connection record can still be saved either way.
- **Process lifecycle**: `TunnelManager::ensureTunnel()` picks a free local port, starts the tunnel, and stores `{pid, local_port}` in cache keyed by connection id. Subsequent requests reuse the existing tunnel if the process is still alive (`posix_kill($pid, 0)` on Unix); otherwise it restarts it. A scheduled command (`php artisan schedule`) closes tunnels idle past a configurable timeout to avoid orphaned processes accumulating on the host.
- **Private key handling**: the decrypted key is written to a temp file with `0600` permissions immediately before launching `ssh`, and deleted right after the process starts (OpenSSH only needs the file at connection time).
- **Known limitation (documented, not built in v1)**: `StrictHostKeyChecking=no` is used for simplicity, which accepts unknown host keys without prompting — a MITM risk on untrusted networks. A host-key-pinning UI is a candidate for a future version, not v1.
- **Disconnect behavior**: "Torna al DB locale" (§4) only clears the session key — it does **not** kill the underlying tunnel process. Tunnels are reaped solely by the idle-timeout scheduled command (not tied to any single session's active/inactive state), so reconnecting shortly after doesn't pay the SSH handshake cost again.

---

## 7. Pro Package — SQL Server Driver

- `SqlServerDriver implements DatabaseDriver`, using `INFORMATION_SCHEMA` queries for tables/columns/foreign keys/indexes, and generating T-SQL for `buildAlterDdl`/`buildCreateDdl`.
- Requires the `pdo_sqlsrv` PHP extension. Same prerequisite-check pattern as SSH: a missing extension blocks connect/test with a specific message, not a cryptic driver error.
- Registered into Core via `config(['dbmyadmin.drivers.sqlsrv' => SqlServerDriver::class])` from Pro's service provider (§2.1) — no Core changes needed to add this or future drivers (e.g. Oracle) later.

---

## 8. Security

- All secrets (DB password, SSH password/private key/passphrase) stored with Laravel's `encrypted` Eloquent cast (`APP_KEY`-derived). Documented risk: rotating `APP_KEY` without re-encrypting existing rows makes stored connections unreadable — call this out explicitly in the Pro README/user manual.
- Connections are hard-scoped to the owning user (global scope on `DatabaseConnection`); no cross-user visibility.
- Active-connection banner (persistent, on List/Browse/Alter/Create pages) and TRUNCATE/DROP confirmation modals both display the active connection's name — mitigates "destructive action against the wrong database" risk, which is the primary safety concern this whole feature introduces.
- Panel access to the Connections resource is gated by the same authorization mechanism already used by `DbMyAdminPlugin::authorize()` (or a Pro-specific equivalent), so it doesn't bypass whatever access control the host app already has configured.

---

## 9. Maintenance Model (Core vs. Pro)

Because Core pages aren't duplicated and the Pro↔Core coupling surface is deliberately small (§2), day-to-day maintenance splits cleanly:

1. **Core-only changes** (bug fixes/features in table browsing, sorting, DDL, etc.) — touch only `lukemyadmin`. Pro inherits them automatically on `composer update` via its `^1.x` constraint on Core. This is the common case.
2. **Pro-only changes** (tunnel manager, SQL Server driver quirks, connection UI) — touch only `lukemyadmin-pro`, zero impact on Core.
3. **Shared-contract changes** (e.g. altering `ActiveConnectionResolver`'s signature) — touch both repos, but this is ordinary library/consumer versioning via semver, not duplicated work, and should be rare given how few and deliberate the extension points are.

### Local development workflow

`dbmyadmin-pro/composer.json` lists **two** repository entries for the Core package, in this order:

```json
"repositories": [
    { "type": "path", "url": "../lukemyadmin", "options": { "symlink": true } },
    { "type": "vcs", "url": "https://github.com/lucapellegrino-70/dbmyadmin" }
]
```

Composer prefers the `path` repository automatically when the sibling directory exists (no tag/push/update cycle needed to pick up local Core edits), and silently falls through to the `vcs` entry when it doesn't (e.g. on a customer's machine, which never has the sibling folder). This is safe to commit permanently — it only affects local dev, never a real install.

**Windows caveat (verified during Pro repo bootstrap, 2026-07-11)**: on a normal (non-elevated, non-Developer-Mode) Windows shell, Composer's `"symlink": true` did **not** fall back to copying as originally feared — it created an NTFS junction instead (Composer logs "Junctioning from ../lukemyadmin"), which doesn't require admin rights and is confirmed to give a genuinely live view of the Core repo (verified via `readlink` and a byte-identical diff against the source). Edits to Core are picked up immediately, no `composer install` re-run needed. The path repo also needed an explicit `options.versions` override (`{"lucapellegrino/dbmyadmin": "1.1.0"}`) — without it, Composer reports the path repo's package as `dev-main` (an unversioned dev reference), which doesn't satisfy a stable constraint like `^1.1`.

---

## 10. Testing Strategy

- **Core**: extend the existing Pest suite to cover the §2 refactor — assert behavior is unchanged when `dbmyadmin.connection` is `null` and no `ActiveConnectionResolver` override is bound (i.e. the entire existing 55-test suite must keep passing untouched).
- **Pro**:
  - `DatabaseConnection` model: encryption round-trip, per-user scoping.
  - `SessionConnectionResolver`: activation/deactivation, fallback to `null` when the referenced connection no longer exists.
  - `SqlServerDriver`: against a SQL Server container in CI where available; otherwise unit-tested against mocked `INFORMATION_SCHEMA` result sets.
  - `TunnelManager`: process lifecycle logic tested against an injectable/fake process runner (a real SSH server in CI is desirable but not guaranteed available everywhere — consider a `linuxserver/openssh-server` Docker container as a stretch goal, not a hard requirement for v1).

---

## Out of Scope (v1)

- Runtime license-key validation/expiry (§1 — access control is the private repo itself).
- Host-key pinning / known_hosts management for SSH (§6 — documented limitation instead).
- Shared/team connections, connection tagging (environment/color labels) — YAGNI, add later if requested.
- Automated license/deploy-key issuance on purchase (manual for v1; webhook automation is a later operational improvement, not part of this spec).

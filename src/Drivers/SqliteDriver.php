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

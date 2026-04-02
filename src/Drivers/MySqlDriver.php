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

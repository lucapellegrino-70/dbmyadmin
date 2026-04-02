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

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

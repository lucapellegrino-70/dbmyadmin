<?php

namespace LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource\Pages;

use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
use LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource;

class CreateTable extends Page
{
    protected static string $resource = DatabaseTableResource::class;

    protected string $view = 'dbmyadmin::pages.create-table';

    // ── Livewire properties ───────────────────────────────────────────────────

    public string $tableName    = '';
    public string $tableComment = '';
    public string $engine       = 'InnoDB';
    public string $charset      = 'utf8mb4';
    public string $collation    = 'utf8mb4_unicode_ci';

    public array $columns     = [];
    public array $foreignKeys = [];
    public string $generatedDdl    = '';
    public array  $validationErrors = [];

    // ─────────────────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->columns = [
            $this->makeColumn([
                'name'           => 'id',
                'type'           => 'BIGINT',
                'length'         => '',
                'unsigned'       => true,
                'nullable'       => false,
                'default'        => '',
                'auto_increment' => true,
                'primary'        => true,
                'unique'         => false,
                'index'          => false,
                'comment'        => '',
            ]),
            $this->makeColumn(),
        ];
        $this->foreignKeys = [];
    }

    // ── Column management ─────────────────────────────────────────────────────

    protected function makeColumn(array $overrides = []): array
    {
        return array_merge([
            'name'           => '',
            'type'           => 'VARCHAR',
            'length'         => '255',
            'unsigned'       => false,
            'nullable'       => true,
            'default'        => '',
            'auto_increment' => false,
            'primary'        => false,
            'unique'         => false,
            'index'          => false,
            'comment'        => '',
        ], $overrides);
    }

    public function addColumn(): void
    {
        $this->columns[] = $this->makeColumn();
        $this->generateDdl();
    }

    public function removeColumn(int $index): void
    {
        array_splice($this->columns, $index, 1);

        if (empty($this->columns)) {
            $this->columns[] = $this->makeColumn();
        }

        $this->generateDdl();
    }

    public function moveColumnUp(int $index): void
    {
        if ($index > 0) {
            [$this->columns[$index - 1], $this->columns[$index]] =
                [$this->columns[$index], $this->columns[$index - 1]];
        }

        $this->generateDdl();
    }

    public function moveColumnDown(int $index): void
    {
        if ($index < count($this->columns) - 1) {
            [$this->columns[$index + 1], $this->columns[$index]] =
                [$this->columns[$index], $this->columns[$index + 1]];
        }

        $this->generateDdl();
    }

    public function updatedColumns(mixed $value, string $key): void
    {
        if (str_ends_with($key, '.type')) {
            $index = (int) explode('.', $key)[0];
            if (isset($this->columns[$index])) {
                $this->columns[$index]['length'] = $this->defaultLengthForType($value);
                if (! $this->isNumericType($value)) {
                    $this->columns[$index]['unsigned']       = false;
                    $this->columns[$index]['auto_increment'] = false;
                }
            }
        }
        $this->generateDdl();
    }

    protected function defaultLengthForType(string $type): string
    {
        return match (strtoupper($type)) {
            'VARCHAR', 'CHAR'    => '255',
            'VARBINARY', 'BINARY' => '255',
            'TINYINT'             => '4',
            'SMALLINT'            => '6',
            'MEDIUMINT'           => '8',
            'INT'                 => '11',
            'BIGINT'              => '20',
            'FLOAT', 'DOUBLE'     => '',
            'DECIMAL', 'NUMERIC'  => '10,2',
            default               => '',
        };
    }

    public function updatedTableName(): void { $this->generateDdl(); }
    public function updatedEngine(): void    { $this->generateDdl(); }
    public function updatedCharset(): void   { $this->generateDdl(); }
    public function updatedCollation(): void { $this->generateDdl(); }

    // ── FK management ─────────────────────────────────────────────────────────

    protected function makeForeignKey(array $overrides = []): array
    {
        return array_merge([
            'column'          => '',
            'ref_table'       => '',
            'ref_column'      => 'id',
            'on_delete'       => 'RESTRICT',
            'on_update'       => 'RESTRICT',
            'constraint_name' => '',
        ], $overrides);
    }

    public function addForeignKey(): void
    {
        $this->foreignKeys[] = $this->makeForeignKey();
        $this->generateDdl();
    }

    public function removeForeignKey(int $index): void
    {
        array_splice($this->foreignKeys, $index, 1);
        $this->generateDdl();
    }

    public function updatedForeignKeys(): void
    {
        $this->generateDdl();
    }

    public function getAvailableTables(): array
    {
        return app(DatabaseDriver::class)->getTables()->pluck('name')->toArray();
    }

    public function getColumnsForTable(string $table): array
    {
        if (empty($table)) {
            return [];
        }

        return app(DatabaseDriver::class)->getColumns($table)->pluck('name')->toArray();
    }

    // ── DDL generation ────────────────────────────────────────────────────────

    public function generateDdl(): void
    {
        $this->validationErrors = [];

        $name = trim($this->tableName);
        if (empty($name)) {
            $this->generatedDdl = '-- Inserisci il nome della tabella per generare il DDL';
            return;
        }

        $lines       = [];
        $primaryKeys = [];
        $indexes     = [];
        $uniques     = [];

        foreach ($this->columns as $col) {
            $colName = trim($col['name'] ?? '');
            if (empty($colName)) {
                continue;
            }

            $type   = $col['type'] ?? 'VARCHAR';
            $length = trim($col['length'] ?? '');

            $typeDef = $type;
            if ($this->typeAcceptsLength($type) && $length !== '') {
                $typeDef = "{$type}({$length})";
            }
            if (($col['unsigned'] ?? false) && $this->isNumericType($type)) {
                $typeDef .= ' UNSIGNED';
            }

            $nullDef    = ($col['nullable'] ?? true) ? 'NULL' : 'NOT NULL';
            $default    = trim($col['default'] ?? '');
            $defaultDef = '';
            if ($default !== '') {
                $defaultDef = ' DEFAULT ' . $this->formatDefault($default, $type);
            } elseif ($col['nullable'] ?? false) {
                $defaultDef = ' DEFAULT NULL';
            }

            $aiDef = ($col['auto_increment'] ?? false) ? ' AUTO_INCREMENT' : '';

            $commentDef = '';
            $comment    = trim($col['comment'] ?? '');
            if ($comment !== '') {
                $escaped    = str_replace("'", "''", $comment);
                $commentDef = " COMMENT '{$escaped}'";
            }

            $lines[] = "    `{$colName}` {$typeDef} {$nullDef}{$defaultDef}{$aiDef}{$commentDef}";

            if ($col['primary'] ?? false) {
                $primaryKeys[] = "`{$colName}`";
            }
            if ($col['unique'] ?? false) {
                $uniques[] = "    UNIQUE KEY `uk_{$colName}` (`{$colName}`)";
            }
            if (($col['index'] ?? false) && ! ($col['unique'] ?? false)) {
                $indexes[] = "    KEY `idx_{$colName}` (`{$colName}`)";
            }
        }

        if (! empty($primaryKeys)) {
            $lines[] = '    PRIMARY KEY (' . implode(', ', $primaryKeys) . ')';
        }
        foreach ($uniques as $u) {
            $lines[] = $u;
        }
        foreach ($indexes as $idx) {
            $lines[] = $idx;
        }

        foreach ($this->foreignKeys as $fk) {
            $localCol  = trim($fk['column'] ?? '');
            $refTable  = trim($fk['ref_table'] ?? '');
            $refCol    = trim($fk['ref_column'] ?? 'id');
            $onDelete  = $fk['on_delete'] ?? 'RESTRICT';
            $onUpdate  = $fk['on_update'] ?? 'RESTRICT';

            if (empty($localCol) || empty($refTable)) {
                continue;
            }

            $constraintName = trim($fk['constraint_name'] ?? '');
            if ($constraintName === '') {
                $constraintName = 'fk_' . trim($this->tableName) . '_' . $localCol;
            }

            $lines[] = "    KEY `idx_{$localCol}` (`{$localCol}`)";
            $lines[] = "    CONSTRAINT `{$constraintName}` FOREIGN KEY (`{$localCol}`)"
                . " REFERENCES `{$refTable}` (`{$refCol}`)"
                . " ON DELETE {$onDelete}"
                . " ON UPDATE {$onUpdate}";
        }

        $tableOptions = "ENGINE={$this->engine}"
            . " DEFAULT CHARSET={$this->charset}"
            . " COLLATE={$this->collation}";

        if ($this->tableComment !== '') {
            $escaped      = str_replace("'", "''", $this->tableComment);
            $tableOptions .= " COMMENT='{$escaped}'";
        }

        $this->generatedDdl = "CREATE TABLE `{$name}` (\n"
            . implode(",\n", $lines) . "\n"
            . ") {$tableOptions};";
    }

    protected function typeAcceptsLength(string $type): bool
    {
        return in_array(strtoupper($type), [
            'VARCHAR', 'CHAR', 'VARBINARY', 'BINARY',
            'TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'BIGINT',
            'FLOAT', 'DOUBLE', 'DECIMAL', 'NUMERIC',
        ]);
    }

    protected function isNumericType(string $type): bool
    {
        return in_array(strtoupper($type), [
            'TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'BIGINT',
            'FLOAT', 'DOUBLE', 'DECIMAL', 'NUMERIC',
        ]);
    }

    protected function formatDefault(string $value, string $type): string
    {
        $upper = strtoupper($value);
        if (in_array($upper, ['NULL', 'CURRENT_TIMESTAMP', 'NOW()', 'CURRENT_DATE', 'CURRENT_TIME', 'TRUE', 'FALSE'])) {
            return $upper;
        }
        if (is_numeric($value)) {
            return $value;
        }

        return "'" . str_replace("'", "''", $value) . "'";
    }

    // ── Execute CREATE TABLE ──────────────────────────────────────────────────

    public function createTable(): void
    {
        $this->validationErrors = [];
        $this->generateDdl();

        if (empty(trim($this->tableName))) {
            $this->validationErrors['tableName'] = 'Il nome della tabella è obbligatorio.';
        } elseif (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', trim($this->tableName))) {
            $this->validationErrors['tableName'] = 'Il nome può contenere solo lettere, numeri e underscore, e non può iniziare con un numero.';
        }

        $hasValidColumn = false;
        foreach ($this->columns as $i => $col) {
            if (trim($col['name'] ?? '') !== '') {
                $hasValidColumn = true;
                if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', trim($col['name']))) {
                    $this->validationErrors["col_{$i}"] = "Nome colonna non valido: '{$col['name']}'.";
                }
            }
        }

        if (! $hasValidColumn) {
            $this->validationErrors['columns'] = 'Definisci almeno una colonna.';
        }

        foreach ($this->foreignKeys as $i => $fk) {
            if (empty(trim($fk['column'] ?? '')) && empty(trim($fk['ref_table'] ?? ''))) {
                continue;
            }
            if (empty(trim($fk['column'] ?? ''))) {
                $this->validationErrors["fk_{$i}_col"] = 'Seleziona la colonna locale per la FK ' . ($i + 1) . '.';
            }
            if (empty(trim($fk['ref_table'] ?? ''))) {
                $this->validationErrors["fk_{$i}_table"] = 'Seleziona la tabella riferita per la FK ' . ($i + 1) . '.';
            }
        }

        if (! empty($this->validationErrors)) {
            return;
        }

        if (empty($this->generatedDdl) || str_starts_with($this->generatedDdl, '--')) {
            $this->validationErrors['ddl'] = 'DDL non valido.';
            return;
        }

        try {
            DB::unprepared($this->generatedDdl);

            Log::info('Tabella creata', [
                'table' => $this->tableName,
                'user'  => auth()->user()?->email ?? 'system',
                'ddl'   => $this->generatedDdl,
            ]);

            Notification::make()
                ->title("Tabella '{$this->tableName}' creata con successo")
                ->success()
                ->send();

            $this->tableName    = '';
            $this->tableComment = '';
            $this->generatedDdl = '';
            $this->foreignKeys  = [];
            $this->mount();

            $this->redirect(DatabaseTableResource::getUrl());
        } catch (\Exception $e) {
            Log::error('Errore creazione tabella', [
                'ddl'   => $this->generatedDdl,
                'error' => $e->getMessage(),
                'user'  => auth()->user()?->email ?? 'system',
            ]);

            $this->validationErrors['sql'] = $e->getMessage();

            Notification::make()
                ->title('Errore durante la creazione')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    // ─────────────────────────────────────────────────────────────────────────

    public function getTitle(): string
    {
        return 'Crea Nuova Tabella';
    }

    public function getBreadcrumbs(): array
    {
        try {
            $indexUrl = DatabaseTableResource::getUrl();
        } catch (\Throwable) {
            $indexUrl = '#';
        }

        return [
            $indexUrl => 'Tabelle Database',
            ''        => 'Crea Nuova Tabella',
        ];
    }
}

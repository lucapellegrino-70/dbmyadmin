<?php

namespace LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource\Pages;

use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
use LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource;

class AlterTable extends Page
{
    protected static string $resource = DatabaseTableResource::class;

    protected string $view = 'dbmyadmin::pages.alter-table';

    // ── Properties ────────────────────────────────────────────────────────────

    public string $tableName          = '';
    public array  $columns            = [];
    public array  $newColumns         = [];
    public string $generatedDdl       = '';
    public array  $validationErrors   = [];

    // ── Mount ─────────────────────────────────────────────────────────────────

    public function mount(string $record): void
    {
        $this->tableName = $record;

        abort_unless(Schema::hasTable($this->tableName), 404);

        $this->loadExistingColumns();
    }

    protected function loadExistingColumns(): void
    {
        $driverColumns = app(DatabaseDriver::class)->getColumns($this->tableName);

        $this->columns = $driverColumns->map(function ($col) {
            $type   = strtoupper($col['type'] ?? 'VARCHAR');
            $length = (string) ($col['length'] ?? '');

            return [
                'name'           => $col['name'],
                'original_name'  => $col['name'],
                'type'           => $type,
                'length'         => $length,
                'unsigned'       => false,
                'nullable'       => (bool) ($col['nullable'] ?? false),
                'default'        => (string) ($col['default'] ?? ''),
                'auto_increment' => str_contains(strtolower((string) ($col['extra'] ?? '')), 'auto_increment'),
                'comment'        => '',
                'action'         => 'keep',
            ];
        })->toArray();
    }

    // ── Existing column management ────────────────────────────────────────────

    public function markModified(int $index): void
    {
        if (isset($this->columns[$index])) {
            $this->columns[$index]['action'] = 'modify';
        }
        $this->generateDdl();
    }

    public function toggleDrop(int $index): void
    {
        if (! isset($this->columns[$index])) {
            return;
        }

        $this->columns[$index]['action'] = $this->columns[$index]['action'] === 'drop'
            ? 'keep'
            : 'drop';

        $this->generateDdl();
    }

    public function updatedColumns(mixed $value, string $key): void
    {
        $parts = explode('.', $key);
        $index = (int) $parts[0];
        $field = $parts[1] ?? '';

        if ($field === 'type' && isset($this->columns[$index])) {
            $this->columns[$index]['length'] = $this->defaultLengthForType($value);
            if (! $this->isNumericType($value)) {
                $this->columns[$index]['unsigned']       = false;
                $this->columns[$index]['auto_increment'] = false;
            }
        }

        if (isset($this->columns[$index])) {
            $this->columns[$index]['action'] = 'modify';
        }

        $this->generateDdl();
    }

    // ── New columns ───────────────────────────────────────────────────────────

    protected function makeNewColumn(array $overrides = []): array
    {
        return array_merge([
            'name'           => '',
            'type'           => 'VARCHAR',
            'length'         => '255',
            'unsigned'       => false,
            'nullable'       => true,
            'default'        => '',
            'auto_increment' => false,
            'comment'        => '',
            'after'          => '',
        ], $overrides);
    }

    public function addNewColumn(): void
    {
        $this->newColumns[] = $this->makeNewColumn();
        $this->generateDdl();
    }

    public function removeNewColumn(int $index): void
    {
        array_splice($this->newColumns, $index, 1);
        $this->generateDdl();
    }

    public function updatedNewColumns(mixed $value, string $key): void
    {
        $parts = explode('.', $key);
        $index = (int) $parts[0];
        $field = $parts[1] ?? '';

        if ($field === 'type' && isset($this->newColumns[$index])) {
            $this->newColumns[$index]['length'] = $this->defaultLengthForType($value);
            if (! $this->isNumericType($value)) {
                $this->newColumns[$index]['unsigned']       = false;
                $this->newColumns[$index]['auto_increment'] = false;
            }
        }

        $this->generateDdl();
    }

    // ── DDL generation ────────────────────────────────────────────────────────

    public function generateDdl(): void
    {
        $this->validationErrors = [];
        $statements             = [];
        $tbl                    = $this->tableName;

        foreach ($this->columns as $col) {
            if ($col['action'] === 'drop') {
                $statements[] = "  DROP COLUMN `{$col['name']}`";
                continue;
            }

            if ($col['action'] === 'modify') {
                $def  = $this->buildColumnDef($col);
                $verb = $col['name'] !== $col['original_name'] ? 'CHANGE COLUMN' : 'MODIFY COLUMN';

                if ($verb === 'CHANGE COLUMN') {
                    $statements[] = "  {$verb} `{$col['original_name']}` `{$col['name']}` {$def}";
                } else {
                    $statements[] = "  {$verb} `{$col['name']}` {$def}";
                }
            }
        }

        foreach ($this->newColumns as $col) {
            $colName = trim($col['name'] ?? '');
            if (empty($colName)) {
                continue;
            }

            $def   = $this->buildColumnDef($col);
            $after = trim($col['after'] ?? '');
            $pos   = ($after !== '' && $after !== '__last__') ? " AFTER `{$after}`" : '';

            $statements[] = "  ADD COLUMN `{$colName}` {$def}{$pos}";
        }

        if (empty($statements)) {
            $this->generatedDdl = '-- Nessuna modifica da applicare';
            return;
        }

        $this->generatedDdl = "ALTER TABLE `{$tbl}`\n"
            . implode(",\n", $statements) . ';';
    }

    protected function buildColumnDef(array $col): string
    {
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

        return "{$typeDef} {$nullDef}{$defaultDef}{$aiDef}{$commentDef}";
    }

    // ── Apply ALTER ───────────────────────────────────────────────────────────

    public function applyChanges(): void
    {
        $this->validationErrors = [];
        $this->generateDdl();

        if (empty($this->generatedDdl) || str_starts_with($this->generatedDdl, '--')) {
            $this->validationErrors['general'] = 'Nessuna modifica da applicare.';
            return;
        }

        foreach ($this->newColumns as $i => $col) {
            if (trim($col['name'] ?? '') !== '' && ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', trim($col['name']))) {
                $this->validationErrors["new_col_{$i}"] = "Nome colonna non valido: '{$col['name']}'";
            }
        }

        if (! empty($this->validationErrors)) {
            return;
        }

        try {
            DB::unprepared($this->generatedDdl);

            Log::info('Tabella modificata', [
                'table' => $this->tableName,
                'ddl'   => $this->generatedDdl,
                'user'  => auth()->user()?->email ?? 'system',
            ]);

            Notification::make()
                ->title("Tabella '{$this->tableName}' modificata con successo")
                ->success()
                ->send();

            $this->newColumns   = [];
            $this->loadExistingColumns();
            $this->generatedDdl = '';
        } catch (\Exception $e) {
            Log::error('Errore ALTER TABLE', [
                'ddl'   => $this->generatedDdl,
                'error' => $e->getMessage(),
                'user'  => auth()->user()?->email ?? 'system',
            ]);

            $this->validationErrors['sql'] = $e->getMessage();

            Notification::make()
                ->title('Errore durante la modifica')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    // ── Type helpers ──────────────────────────────────────────────────────────

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

    protected function defaultLengthForType(string $type): string
    {
        return match (strtoupper($type)) {
            'VARCHAR', 'CHAR'     => '255',
            'VARBINARY', 'BINARY' => '255',
            'TINYINT'             => '4',
            'SMALLINT'            => '6',
            'MEDIUMINT'           => '8',
            'INT'                 => '11',
            'BIGINT'              => '20',
            'DECIMAL', 'NUMERIC'  => '10,2',
            default               => '',
        };
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

    public function getAvailableColumns(): array
    {
        return array_map(fn ($c) => $c['name'], $this->columns);
    }

    // ── Navigation ────────────────────────────────────────────────────────────

    public function getTitle(): string
    {
        return "Modifica struttura: {$this->tableName}";
    }

    public function getBreadcrumbs(): array
    {
        try {
            $indexUrl  = DatabaseTableResource::getUrl();
            $browseUrl = DatabaseTableResource::getUrl('browse', ['record' => $this->tableName]);
        } catch (\Throwable) {
            $indexUrl  = '#';
            $browseUrl = '#';
        }

        return [
            $indexUrl  => 'Tabelle Database',
            $browseUrl => $this->tableName,
            ''         => 'Modifica struttura',
        ];
    }
}

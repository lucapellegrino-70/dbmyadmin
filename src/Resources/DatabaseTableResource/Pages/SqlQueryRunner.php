<?php

namespace LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource\Pages;

use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
use LucaPellegrino\DbMyAdmin\Models\SavedQuery;
use LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource;

class SqlQueryRunner extends Page
{
    protected static string $resource = DatabaseTableResource::class;

    protected static string $view = 'dbmyadmin::pages.sql-query-runner';

    // ── Editor properties ─────────────────────────────────────────────────────

    public string $query        = '';
    public string $errorMessage = '';
    public int    $limitRows    = 500;

    public array $results    = [];
    public array $columns    = [];
    public int   $totalRows  = 0;
    public float $execTimeMs = 0;
    public bool  $hasResults = false;
    public bool  $hasRun     = false;

    public array $schemaMap = [];

    // ── Saved queries properties ──────────────────────────────────────────────

    public array  $savedQueries    = [];
    public string $searchQuery     = '';
    public bool   $showSaveModal   = false;
    public string $saveName        = '';
    public string $saveDescription = '';
    public ?int   $editingQueryId  = null;
    public string $saveError       = '';

    // ─────────────────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->schemaMap = $this->buildSchemaMap();
        $this->loadSavedQueries();
    }

    // ── Schema autocomplete ───────────────────────────────────────────────────

    protected function buildSchemaMap(): array
    {
        $driver = app(DatabaseDriver::class);
        $map    = [];

        foreach ($driver->getTables() as $table) {
            $tableName       = $table['name'];
            $map[$tableName] = $driver->getColumns($tableName)->pluck('name')->toArray();
        }

        return $map;
    }

    // ── Query execution ───────────────────────────────────────────────────────

    public function runQuery(): void
    {
        $this->reset(['results', 'columns', 'errorMessage', 'hasResults', 'totalRows', 'execTimeMs']);
        $this->hasRun = true;

        $sql = trim($this->query);

        if (empty($sql)) {
            $this->errorMessage = 'Inserisci una query SQL prima di eseguire.';
            return;
        }

        $blocked = $this->isBlockedStatement($sql);
        if ($blocked) {
            $this->errorMessage = "Statement non consentito: {$blocked}.";
            return;
        }

        try {
            $start    = microtime(true);
            $upperSql = strtoupper(ltrim($sql));

            if (
                str_starts_with($upperSql, 'SELECT') ||
                str_starts_with($upperSql, 'SHOW')   ||
                str_starts_with($upperSql, 'DESCRIBE') ||
                str_starts_with($upperSql, 'EXPLAIN')
            ) {
                $rows = DB::select($sql);

                $this->execTimeMs = round((microtime(true) - $start) * 1000, 2);
                $this->totalRows  = count($rows);

                if (! empty($rows)) {
                    $this->columns    = array_keys((array) $rows[0]);
                    $limited          = array_slice($rows, 0, $this->limitRows);
                    $this->results    = array_map(fn ($r) => array_map(
                        fn ($v) => $v === null ? 'NULL' : (string) $v,
                        (array) $r
                    ), $limited);
                }
                $this->hasResults = true;
            } else {
                $affected = DB::affectingStatement($sql);

                $this->execTimeMs = round((microtime(true) - $start) * 1000, 2);
                $this->totalRows  = $affected;
                $this->hasResults = false;

                Notification::make()
                    ->title('Query eseguita')
                    ->body("Righe interessate: {$affected}")
                    ->success()
                    ->send();
            }

            Log::info('SQL Query eseguita', [
                'sql'  => $sql,
                'rows' => $this->totalRows,
                'ms'   => $this->execTimeMs,
                'user' => auth()->user()?->email ?? 'system',
            ]);
        } catch (\Exception $e) {
            $this->execTimeMs   = round((microtime(true) - $start) * 1000, 2);
            $this->errorMessage = $e->getMessage();

            Log::warning('SQL Query error', [
                'sql'   => $sql,
                'error' => $e->getMessage(),
                'user'  => auth()->user()?->email ?? 'system',
            ]);
        }
    }

    public function clearQuery(): void
    {
        $this->reset(['query', 'results', 'columns', 'errorMessage',
                      'hasResults', 'hasRun', 'totalRows', 'execTimeMs']);
    }

    // ── Saved queries ─────────────────────────────────────────────────────────

    protected function loadSavedQueries(): void
    {
        $q = SavedQuery::orderByDesc('updated_at');

        if (! empty(trim($this->searchQuery))) {
            $search = '%' . trim($this->searchQuery) . '%';
            $q->where(function ($query) use ($search) {
                $query->where('name', 'like', $search)
                      ->orWhere('description', 'like', $search)
                      ->orWhere('sql', 'like', $search);
            });
        }

        $this->savedQueries = $q->get()->map(fn ($item) => [
            'id'          => $item->id,
            'name'        => $item->name,
            'description' => $item->description ?? '',
            'sql'         => $item->sql,
            'sql_preview' => $item->sql_preview,
            'created_by'  => $item->created_by ?? '',
            'updated_at'  => $item->updated_at?->format('d/m/Y H:i'),
        ])->toArray();
    }

    public function updatedSearchQuery(): void
    {
        $this->loadSavedQueries();
    }

    public function openSaveModal(): void
    {
        $this->saveError       = '';
        $this->editingQueryId  = null;
        $this->saveName        = '';
        $this->saveDescription = '';
        $this->showSaveModal   = true;
    }

    public function openEditModal(int $id): void
    {
        $query = SavedQuery::find($id);
        if (! $query) {
            return;
        }

        $this->saveError       = '';
        $this->editingQueryId  = $id;
        $this->saveName        = $query->name;
        $this->saveDescription = $query->description ?? '';
        $this->showSaveModal   = true;
    }

    public function closeSaveModal(): void
    {
        $this->showSaveModal = false;
        $this->saveError     = '';
    }

    public function saveQuery(): void
    {
        $this->saveError = '';
        $name            = trim($this->saveName);
        $sql             = trim($this->query);

        if (empty($name)) {
            $this->saveError = 'Il nome è obbligatorio.';
            return;
        }

        if (empty($sql)) {
            $this->saveError = 'Non c\'è nessuna query nell\'editor da salvare.';
            return;
        }

        if ($this->editingQueryId) {
            $query = SavedQuery::find($this->editingQueryId);
            if ($query) {
                $query->update([
                    'name'        => $name,
                    'description' => trim($this->saveDescription) ?: null,
                    'sql'         => $sql,
                ]);
                Notification::make()->title('Query aggiornata')->success()->send();
            }
        } else {
            SavedQuery::create([
                'name'        => $name,
                'description' => trim($this->saveDescription) ?: null,
                'sql'         => $sql,
                'created_by'  => auth()->user()?->email ?? 'system',
            ]);
            Notification::make()->title('Query salvata')->success()->send();
        }

        $this->showSaveModal   = false;
        $this->saveName        = '';
        $this->saveDescription = '';
        $this->editingQueryId  = null;
        $this->loadSavedQueries();
    }

    public function useSavedQuery(int $id): void
    {
        $query = SavedQuery::find($id);
        if (! $query) {
            return;
        }

        $this->query = $query->sql;
        $this->reset(['results', 'columns', 'errorMessage', 'hasResults', 'hasRun', 'totalRows', 'execTimeMs']);

        Notification::make()->title('Query caricata nell\'editor')->success()->send();
    }

    public function deleteSavedQuery(int $id): void
    {
        SavedQuery::find($id)?->delete();
        $this->loadSavedQueries();

        Notification::make()->title('Query eliminata')->success()->send();
    }

    public function loadTemplate(string $template): void
    {
        $replace = [
            'select_all'   => "SELECT *\nFROM table_name\nLIMIT 100;",
            'select_where' => "SELECT *\nFROM table_name\nWHERE column_name = 'value'\nLIMIT 100;",
            'count'        => "SELECT COUNT(*) AS total\nFROM table_name;",
            'group_by'     => "SELECT column_name, COUNT(*) AS total\nFROM table_name\nGROUP BY column_name\nORDER BY total DESC;",
            'join'         => "SELECT a.*, b.column_name\nFROM table_a a\nINNER JOIN table_b b ON a.id = b.table_a_id\nLIMIT 100;",
            'show_tables'  => "SHOW TABLES;",
            'show_columns' => "DESCRIBE table_name;",
            'insert'       => "INSERT INTO table_name (column1, column2, column3)\nVALUES ('value1', 'value2', 'value3');",
            'update'       => "UPDATE table_name\nSET column1 = 'value1', column2 = 'value2'\nWHERE id = 1;",
            'delete'       => "DELETE FROM table_name\nWHERE id = 1;",
        ];

        $append = [
            'from'       => "\nFROM table_name",
            'left_join'  => "\nLEFT JOIN table_name ON a.id = table_name.a_id",
            'right_join' => "\nRIGHT JOIN table_name ON a.id = table_name.a_id",
            'order_by'   => "\nORDER BY column_name ASC",
        ];

        if (isset($replace[$template])) {
            $this->query = $replace[$template];
        } elseif (isset($append[$template])) {
            $this->query = rtrim($this->query) . $append[$template];
        }
    }

    // ── Security ──────────────────────────────────────────────────────────────

    protected function isBlockedStatement(string $sql): ?string
    {
        $blocked = config('dbmyadmin.query_runner.blocked_statements', [
            'DROP', 'TRUNCATE', 'ALTER', 'CREATE',
            'RENAME', 'GRANT', 'REVOKE', 'LOCK', 'UNLOCK',
        ]);

        $upper = strtoupper(ltrim($sql));

        foreach ($blocked as $keyword) {
            if (str_starts_with($upper, $keyword)) {
                return $keyword;
            }
        }

        return null;
    }

    // ── Navigation ────────────────────────────────────────────────────────────

    public function getTitle(): string
    {
        return 'Query SQL';
    }

    public function getBreadcrumbs(): array
    {
        return [
            DatabaseTableResource::getUrl() => 'Tabelle Database',
            ''                              => 'Query SQL',
        ];
    }
}

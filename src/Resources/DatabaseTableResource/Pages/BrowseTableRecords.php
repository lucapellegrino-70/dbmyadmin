<?php

namespace LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource\Pages;

use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
use LucaPellegrino\DbMyAdmin\Models\DynamicTableModel;
use LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource;

class BrowseTableRecords extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = DatabaseTableResource::class;

    protected static string $view = 'dbmyadmin::pages.browse-table-records';

    // ── Candidates for "label" column in related FK tables ────────────────────
    protected array $labelColumnCandidates = [
        'name', 'nome', 'title', 'titolo',
        'description', 'descrizione',
        'code', 'codice',
        'label', 'etichetta',
        'slug', 'email',
        'username', 'surname', 'cognome',
        'first_name', 'last_name',
        'matricola',
    ];

    // ── Instance properties ───────────────────────────────────────────────────

    public string $tableName    = '';
    public array  $tableColumns = [];

    /**
     * FK map detected via the driver.
     * Must be public — Livewire serialises only public properties between requests.
     */
    public array $detectedForeignKeys = [];

    /** Resolved FK config cache (public for same reason). */
    public array $fkConfigCache = [];

    /** FK select-options cache (public for same reason). */
    public array $fkOptionsCache = [];

    // ── Mount ─────────────────────────────────────────────────────────────────

    public function mount(string $record): void
    {
        $this->tableName    = $record;
        $this->tableColumns = $this->resolveTableColumns();

        abort_unless(
            ! in_array($this->tableName, config('dbmyadmin.excluded_tables', []))
                && $this->tableExists(),
            404,
            "Tabella '{$this->tableName}' non trovata o non accessibile."
        );

        $this->detectedForeignKeys = $this->detectForeignKeys();
    }

    // ── Table helpers ─────────────────────────────────────────────────────────

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

        return app(DatabaseDriver::class)
            ->getColumns($this->tableName)
            ->toArray();
    }

    protected function getPrimaryKey(): string
    {
        foreach ($this->tableColumns as $col) {
            if ($col['key'] === 'PRI') {
                return $col['name'];
            }
        }

        return 'id';
    }

    protected function isAutoIncrement(string $column): bool
    {
        foreach ($this->tableColumns as $col) {
            if ($col['name'] === $column && str_contains((string) ($col['extra'] ?? ''), 'auto_increment')) {
                return true;
            }
        }

        return false;
    }

    protected function isTimestampColumn(string $column): bool
    {
        return in_array($column, ['created_at', 'updated_at', 'deleted_at']);
    }

    // ── FK detection via driver ───────────────────────────────────────────────

    protected function detectForeignKeys(): array
    {
        return app(DatabaseDriver::class)
            ->getForeignKeys($this->tableName)
            ->keyBy('column')
            ->map(fn ($fk) => [
                'foreign_table'  => $fk['referenced_table'],
                'foreign_column' => $fk['referenced_column'],
            ])
            ->toArray();
    }

    /**
     * Auto-detects label columns on a related table using the driver.
     *
     * Strategy:
     *   1. Check $labelColumnCandidates against the related table columns (text types only).
     *   2. Concatenate up to 3 matches.
     *   3. Fall back to the PK if nothing found.
     *
     * @return array{labelColumns: string[], separator: string}
     */
    protected function autoDetectLabelColumns(string $relatedTable, string $relatedPk): array
    {
        $columns = app(DatabaseDriver::class)->getColumns($relatedTable);

        $textTypes = ['VARCHAR', 'CHAR', 'TINYTEXT', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT', 'ENUM'];

        $available = $columns->pluck('type', 'name')->toArray();

        $found = [];
        foreach ($this->labelColumnCandidates as $candidate) {
            if (
                isset($available[$candidate])
                && in_array(strtoupper($available[$candidate]), $textTypes)
                && $candidate !== $relatedPk
            ) {
                $found[] = $candidate;
                if (count($found) >= 3) {
                    break;
                }
            }
        }

        if (empty($found)) {
            return ['labelColumns' => [$relatedPk], 'separator' => ''];
        }

        return ['labelColumns' => $found, 'separator' => ' – '];
    }

    /**
     * Returns the full FK config for a column, or null if not a FK.
     * Result is cached.
     */
    protected function getFkConfig(string $column): ?array
    {
        if (array_key_exists($column, $this->fkConfigCache)) {
            return $this->fkConfigCache[$column];
        }

        if (empty($this->detectedForeignKeys)) {
            $this->detectedForeignKeys = $this->detectForeignKeys();
        }

        $detected = $this->detectedForeignKeys[$column] ?? null;

        if ($detected === null) {
            return $this->fkConfigCache[$column] = null;
        }

        $relTable  = $detected['foreign_table'];
        $relKey    = $detected['foreign_column'];
        $autoLabel = $this->autoDetectLabelColumns($relTable, $relKey);

        return $this->fkConfigCache[$column] = [
            'table'      => $relTable,
            'key'        => $relKey,
            'label'      => $autoLabel['labelColumns'],
            'separator'  => $autoLabel['separator'],
            'searchable' => $autoLabel['labelColumns'],
        ];
    }

    /**
     * Returns select options for a FK column. Format: [id => "label", …]
     */
    protected function getFkOptions(string $column): array
    {
        if (isset($this->fkOptionsCache[$column])) {
            return $this->fkOptionsCache[$column];
        }

        $config = $this->getFkConfig($column);
        if ($config === null) {
            return [];
        }

        $relTable     = $config['table'];
        $relKey       = $config['key'];
        $labelColumns = (array) $config['label'];
        $sep          = $config['separator'] ?? ' – ';
        $selectCols   = array_unique(array_merge([$relKey], $labelColumns));

        $rows    = DB::table($relTable)->select($selectCols)->orderBy($labelColumns[0])->get();
        $options = [];

        foreach ($rows as $row) {
            $row        = (array) $row;
            $labelParts = array_map(fn ($c) => $row[$c] ?? '', $labelColumns);
            $options[$row[$relKey]] = implode($sep, array_filter($labelParts, fn ($v) => $v !== ''));
        }

        return $this->fkOptionsCache[$column] = $options;
    }

    protected function resolveFkLabel(string $column, mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        return $this->getFkOptions($column)[$value] ?? (string) $value;
    }

    // ── Query builder ─────────────────────────────────────────────────────────

    protected function buildQuery(): Builder
    {
        return (new DynamicTableModel($this->tableName, $this->getPrimaryKey()))->newQuery();
    }

    // ── Filament Table ────────────────────────────────────────────────────────

    public function table(Table $table): Table
    {
        return $table
            ->query($this->buildQuery())
            ->columns($this->buildTableColumns())
            ->actions([
                Tables\Actions\Action::make('edit_record')
                    ->label('Modifica')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->form(fn () => $this->buildFormSchema(isEdit: true))
                    ->fillForm(function ($record): array {
                        return $record instanceof \Illuminate\Database\Eloquent\Model
                            ? $record->getAttributes()
                            : (array) $record;
                    })
                    ->action(function (array $data, $record): void {
                        $original = $record instanceof \Illuminate\Database\Eloquent\Model
                            ? $record->getAttributes()
                            : (array) $record;
                        $this->performUpdate($original, $data);
                    }),

                Tables\Actions\Action::make('delete_record')
                    ->label('Elimina')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Conferma eliminazione')
                    ->modalDescription('Sei sicuro di voler eliminare questo record? L\'operazione è irreversibile.')
                    ->modalSubmitActionLabel('Sì, elimina')
                    ->action(fn ($record) => $this->performDelete(
                        $record instanceof \Illuminate\Database\Eloquent\Model
                            ? $record->getAttributes()
                            : (array) $record
                    )),
            ])
            ->headerActions([
                Tables\Actions\Action::make('create_record')
                    ->label('Nuovo Record')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->form(fn () => $this->buildFormSchema(isEdit: false))
                    ->action(fn (array $data) => $this->performCreate($data)),
            ])
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Nessun record trovato')
            ->emptyStateDescription("La tabella '{$this->tableName}' è vuota.");
    }

    // ── Table columns ─────────────────────────────────────────────────────────

    protected function buildTableColumns(): array
    {
        $columns = [];

        foreach ($this->tableColumns as $col) {
            $name    = $col['name'];
            $type    = strtolower($this->fullType($col));
            $fkConf  = $this->getFkConfig($name);

            if ($fkConf !== null) {
                $columns[] = Tables\Columns\TextColumn::make($name)
                    ->label($this->formatLabel($name))
                    ->formatStateUsing(fn ($state) => $this->resolveFkLabel($name, $state))
                    ->sortable()
                    ->searchable();
            } else {
                $column = Tables\Columns\TextColumn::make($name)
                    ->label($this->formatLabel($name))
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: $this->isTimestampColumn($name));

                if ($this->isDateTimeType($type)) {
                    $column = $column->dateTime('d/m/Y H:i:s');
                } elseif ($this->isDateOnlyType($type)) {
                    $column = $column->date('d/m/Y');
                } elseif ($this->isTimeType($type)) {
                    $column = $column->fontFamily('mono');
                } elseif ($this->isNumericType($type)) {
                    $column = $column->numeric();
                }

                $columns[] = $column;
            }
        }

        return $columns;
    }

    // ── Form schema ───────────────────────────────────────────────────────────

    protected function buildFormSchema(bool $isEdit): array
    {
        $fields = [];
        $pk     = $this->getPrimaryKey();

        foreach ($this->tableColumns as $col) {
            $name     = $col['name'];
            $type     = strtolower($this->fullType($col));
            $nullable = (bool) $col['nullable'];

            if ($name === $pk && $this->isAutoIncrement($name)) {
                if ($isEdit) {
                    $fields[] = Forms\Components\TextInput::make($name)
                        ->label($this->formatLabel($name))
                        ->disabled()
                        ->dehydrated(false);
                }
                continue;
            }

            if ($this->isTimestampColumn($name)) {
                continue;
            }

            $fkConf = $this->getFkConfig($name);
            if ($fkConf !== null) {
                $field = Forms\Components\Select::make($name)
                    ->label($this->formatLabel($name))
                    ->options($this->getFkOptions($name))
                    ->searchable()
                    ->preload();

                $fields[] = $nullable ? $field->nullable() : $field->required();
                continue;
            }

            $field    = $this->buildFormField($name, $type, $nullable, $col);
            $fields[] = $nullable ? $field->nullable() : $field->required();
        }

        return $fields;
    }

    protected function buildFormField(string $name, string $type, bool $nullable, array $col): Forms\Components\Field
    {
        $label = $this->formatLabel($name);

        if ($type === 'tinyint(1)') {
            return Forms\Components\Toggle::make($name)->label($label);
        }

        if ($this->isIntegerType($type)) {
            return Forms\Components\TextInput::make($name)->label($label)->numeric()->integer();
        }

        if ($this->isDecimalType($type)) {
            return Forms\Components\TextInput::make($name)->label($label)->numeric();
        }

        if ($type === 'time') {
            return Forms\Components\TimePicker::make($name)->label($label)->seconds(true);
        }

        if (str_starts_with($type, 'date') && ! str_starts_with($type, 'datetime')) {
            return Forms\Components\DatePicker::make($name)->label($label)->displayFormat('d/m/Y');
        }

        if (str_starts_with($type, 'datetime') || str_starts_with($type, 'timestamp')) {
            return Forms\Components\DateTimePicker::make($name)->label($label)->displayFormat('d/m/Y H:i:s');
        }

        if (str_starts_with($type, 'enum')) {
            preg_match("/enum\((.+)\)/i", $type, $matches);
            $options = [];
            if (! empty($matches[1])) {
                foreach (str_getcsv($matches[1], ',', "'") as $val) {
                    $clean           = trim($val, "' ");
                    $options[$clean] = $clean;
                }
            }

            return Forms\Components\Select::make($name)->label($label)->options($options);
        }

        if (in_array($type, ['text', 'mediumtext', 'longtext', 'tinytext']) || str_starts_with($type, 'text')) {
            return Forms\Components\Textarea::make($name)->label($label)->rows(4)->columnSpanFull();
        }

        if ($type === 'json') {
            return Forms\Components\Textarea::make($name)
                ->label($label . ' (JSON)')
                ->rows(5)
                ->columnSpanFull()
                ->hint('Inserisci JSON valido');
        }

        $maxLength = $this->extractLength($type);
        $input     = Forms\Components\TextInput::make($name)->label($label);

        if ($maxLength !== null) {
            $input = $input->maxLength($maxLength);
        }

        return $input;
    }

    // ── CRUD operations ───────────────────────────────────────────────────────

    protected function performCreate(array $data): void
    {
        try {
            if (Schema::hasColumn($this->tableName, 'created_at') && ! isset($data['created_at'])) {
                $data['created_at'] = now();
            }
            if (Schema::hasColumn($this->tableName, 'updated_at') && ! isset($data['updated_at'])) {
                $data['updated_at'] = now();
            }

            DB::table($this->tableName)->insert($data);

            Log::info('Record creato', ['table' => $this->tableName, 'user' => auth()->user()?->email ?? 'system']);
            Notification::make()->title('Record creato con successo')->success()->send();
        } catch (\Exception $e) {
            Log::error('Errore creazione record', ['table' => $this->tableName, 'error' => $e->getMessage()]);
            Notification::make()->title('Errore durante la creazione')->body($e->getMessage())->danger()->send();
        }
    }

    protected function performUpdate(array $original, array $data): void
    {
        $pk = $this->getPrimaryKey();

        try {
            if (Schema::hasColumn($this->tableName, 'updated_at')) {
                $data['updated_at'] = now();
            }

            DB::table($this->tableName)->where($pk, $original[$pk])->update($data);

            Log::info('Record aggiornato', [
                'table' => $this->tableName,
                'pk'    => $original[$pk],
                'user'  => auth()->user()?->email ?? 'system',
            ]);
            Notification::make()->title('Record aggiornato con successo')->success()->send();
        } catch (\Exception $e) {
            Log::error('Errore aggiornamento record', ['table' => $this->tableName, 'error' => $e->getMessage()]);
            Notification::make()->title('Errore durante l\'aggiornamento')->body($e->getMessage())->danger()->send();
        }
    }

    protected function performDelete(array $record): void
    {
        $pk = $this->getPrimaryKey();

        try {
            DB::table($this->tableName)->where($pk, $record[$pk])->delete();

            Log::info('Record eliminato', [
                'table' => $this->tableName,
                'pk'    => $record[$pk],
                'user'  => auth()->user()?->email ?? 'system',
            ]);
            Notification::make()->title('Record eliminato con successo')->success()->send();
        } catch (\Exception $e) {
            Log::error('Errore eliminazione record', ['table' => $this->tableName, 'error' => $e->getMessage()]);
            Notification::make()->title('Errore durante l\'eliminazione')->body($e->getMessage())->danger()->send();
        }
    }

    // ── Type helpers ──────────────────────────────────────────────────────────

    /**
     * Reconstructs the full type string (e.g. 'varchar(255)') from driver column data.
     */
    protected function fullType(array $col): string
    {
        $type   = strtolower($col['type'] ?? '');
        $length = $col['length'] ?? null;

        return ($length !== null && $length !== '') ? "{$type}({$length})" : $type;
    }

    protected function isIntegerType(string $type): bool
    {
        return (bool) preg_match('/^(tinyint(?!\(1\))|smallint|mediumint|int|bigint)/i', $type);
    }

    protected function isDecimalType(string $type): bool
    {
        return (bool) preg_match('/^(float|double|decimal|numeric)/i', $type);
    }

    protected function isNumericType(string $type): bool
    {
        return $this->isIntegerType($type) || $this->isDecimalType($type);
    }

    protected function isDateTimeType(string $type): bool
    {
        return (bool) preg_match('/^(datetime|timestamp)/i', $type);
    }

    protected function isDateOnlyType(string $type): bool
    {
        return $type === 'date';
    }

    protected function isTimeType(string $type): bool
    {
        return $type === 'time';
    }

    protected function extractLength(string $type): ?int
    {
        preg_match('/\((\d+)\)/', $type, $matches);

        return isset($matches[1]) ? (int) $matches[1] : null;
    }

    protected function formatLabel(string $column): string
    {
        return ucwords(str_replace('_', ' ', $column));
    }

    // ── Navigation ────────────────────────────────────────────────────────────

    public function getTitle(): string
    {
        return "Sfoglia: {$this->tableName}";
    }

    public function getBreadcrumbs(): array
    {
        return [
            DatabaseTableResource::getUrl() => 'Tabelle Database',
            ''                              => "Sfoglia: {$this->tableName}",
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('alter_table')
                ->label('Modifica struttura')
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('warning')
                ->url(DatabaseTableResource::getUrl('alter-table', ['record' => $this->tableName])),

            Action::make('back')
                ->label('← Torna alle Tabelle')
                ->url(DatabaseTableResource::getUrl())
                ->color('gray'),
        ];
    }
}

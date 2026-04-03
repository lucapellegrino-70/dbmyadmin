<?php

namespace LucaPellegrino\DbMyAdmin\Resources;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;
use LucaPellegrino\DbMyAdmin\DbMyAdminPlugin;
use LucaPellegrino\DbMyAdmin\Models\DatabaseTable;
use LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource\Pages;

class DatabaseTableResource extends \Filament\Resources\Resource
{
    protected static ?string $model = DatabaseTable::class;

    protected static ?string $navigationLabel = 'Gestione Tabelle DB';

    protected static ?string $modelLabel = 'Tabella Database';

    protected static ?string $pluralModelLabel = 'Tabelle Database';

    protected static ?int $navigationSort = 99;

    public static function getNavigationGroup(): ?string
    {
        return filament()->getCurrentPanel()?->getPlugin('dbmyadmin')?->getNavigationGroup();
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return filament()->getCurrentPanel()?->getPlugin('dbmyadmin')?->getNavigationIcon()
            ?? 'heroicon-o-circle-stack';
    }

    public static function canViewAny(): bool
    {
        $plugin = filament()->getCurrentPanel()?->getPlugin('dbmyadmin');

        return $plugin instanceof DbMyAdminPlugin
            ? $plugin->isAuthorized()
            : true;
    }

    /**
     * Returns column definitions for the given table via the driver.
     * Result is keyed by driver format: name, type, nullable (bool), default, key, extra, length.
     */
    public static function getTableColumns(string $tableName): \Illuminate\Support\Collection
    {
        return app(DatabaseDriver::class)->getColumns($tableName);
    }

    /**
     * Formats bytes into a human-readable string.
     */
    public static function formatBytes(?int $bytes, int $precision = 2): string
    {
        if ($bytes === null || $bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $base  = log($bytes, 1024);
        $pow   = floor($base);

        return round(pow(1024, $base - $pow), $precision) . ' ' . $units[$pow];
    }

    /**
     * Exits any open transaction before executing DDL.
     * TRUNCATE in MySQL causes an implicit commit and cannot run inside a transaction.
     */
    protected static function rollbackFilamentTransaction(): void
    {
        try {
            DB::rollBack();
        } catch (\Throwable) {
        }
    }

    protected static function disableForeignKeyChecks(): void
    {
        match (DB::getDriverName()) {
            'mysql', 'mariadb' => DB::unprepared('SET FOREIGN_KEY_CHECKS=0'),
            'pgsql'            => DB::unprepared('SET session_replication_role = replica'),
            'sqlite'           => DB::unprepared('PRAGMA foreign_keys = OFF'),
            default            => null,
        };
    }

    protected static function enableForeignKeyChecks(): void
    {
        match (DB::getDriverName()) {
            'mysql', 'mariadb' => DB::unprepared('SET FOREIGN_KEY_CHECKS=1'),
            'pgsql'            => DB::unprepared('SET session_replication_role = DEFAULT'),
            'sqlite'           => DB::unprepared('PRAGMA foreign_keys = ON'),
            default            => null,
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome Tabella')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('rows')
                    ->label('Numero Righe')
                    ->numeric()
                    ->sortable()
                    ->alignEnd()
                    ->color(fn ($state) => $state > 10000 ? 'warning' : 'success'),

                Tables\Columns\TextColumn::make('data_length')
                    ->label('Dimensione Dati')
                    ->formatStateUsing(fn ($state) => static::formatBytes($state))
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('index_length')
                    ->label('Dimensione Indici')
                    ->formatStateUsing(fn ($state) => static::formatBytes($state))
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('total_size')
                    ->label('Dimensione Totale')
                    ->formatStateUsing(fn ($state) => static::formatBytes($state))
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('engine')
                    ->label('Engine')
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('collation')
                    ->label('Collation')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Action::make('browse')
                    ->label('Sfoglia')
                    ->icon('heroicon-o-table-cells')
                    ->color('success')
                    ->iconButton()
                    ->tooltip('Sfoglia')
                    ->url(fn ($record) => static::getUrl('browse', ['record' => $record->name])),

                Action::make('view_structure')
                    ->label('Struttura')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->iconButton()
                    ->tooltip('Struttura')
                    ->modalHeading(fn ($record) => 'Struttura: ' . $record->name)
                    ->modalContent(fn ($record) => view('dbmyadmin::table-structure', [
                        'columns' => static::getTableColumns($record->name),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Chiudi'),

                Action::make('truncate')
                    ->label('Svuota Tabella')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->iconButton()
                    ->tooltip('Svuota tabella')
                    ->requiresConfirmation()
                    ->modalHeading('Conferma Svuotamento Tabella')
                    ->modalDescription(fn ($record) => "Sei sicuro di voler svuotare la tabella '{$record->name}'? Questa operazione eliminerà tutti i dati in modo permanente.")
                    ->modalSubmitActionLabel('Sì, svuota tabella')
                    ->action(function ($record) {
                        $tableName = $record->name;

                        try {
                            static::rollbackFilamentTransaction();
                            DB::table($tableName)->truncate();
                            DatabaseTable::clearCache();

                            Log::info('Tabella svuotata', [
                                'table' => $tableName,
                                'user'  => auth()->user()?->email ?? 'system',
                            ]);

                            Notification::make()
                                ->title('Tabella svuotata con successo')
                                ->body("La tabella '{$tableName}' è stata svuotata")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Log::error('Errore svuotamento tabella', [
                                'table' => $tableName,
                                'error' => $e->getMessage(),
                            ]);

                            Notification::make()
                                ->title('Errore durante lo svuotamento')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('truncate_with_fk')
                    ->label('Svuota (disabilita FK)')
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('warning')
                    ->iconButton()
                    ->tooltip('Svuota (disabilita FK)')
                    ->requiresConfirmation()
                    ->modalHeading('Conferma Svuotamento con Disabilitazione FK')
                    ->modalDescription(fn ($record) => "Sei sicuro di voler svuotare la tabella '{$record->name}' disabilitando temporaneamente i controlli sulle chiavi esterne? Questa operazione eliminerà tutti i dati in modo permanente.")
                    ->modalSubmitActionLabel('Sì, svuota con FK disabilitate')
                    ->action(function ($record) {
                        $tableName = $record->name;

                        try {
                            static::rollbackFilamentTransaction();
                            static::disableForeignKeyChecks();
                            DB::table($tableName)->truncate();
                            static::enableForeignKeyChecks();
                            DatabaseTable::clearCache();

                            Log::info('Tabella svuotata con FK disabilitate', [
                                'table' => $tableName,
                                'user'  => auth()->user()?->email ?? 'system',
                            ]);

                            Notification::make()
                                ->title('Tabella svuotata con successo')
                                ->body("La tabella '{$tableName}' è stata svuotata (FK disabilitate)")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            try {
                                static::enableForeignKeyChecks();
                            } catch (\Throwable) {
                            }

                            Log::error('Errore svuotamento tabella con FK', [
                                'table' => $tableName,
                                'error' => $e->getMessage(),
                            ]);

                            Notification::make()
                                ->title('Errore durante lo svuotamento')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                BulkAction::make('truncate_selected')
                    ->label('Svuota Tabelle Selezionate')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Conferma Svuotamento Multiplo')
                    ->modalDescription('Sei sicuro di voler svuotare tutte le tabelle selezionate? Questa operazione eliminerà tutti i dati in modo permanente.')
                    ->modalSubmitActionLabel('Sì, svuota tabelle')
                    ->action(function (Collection $records) {
                        static::rollbackFilamentTransaction();

                        $success = 0;
                        $errors  = 0;

                        foreach ($records as $record) {
                            try {
                                DB::table($record->name)->truncate();
                                $success++;

                                Log::info('Tabella svuotata (bulk)', [
                                    'table' => $record->name,
                                    'user'  => auth()->user()?->email ?? 'system',
                                ]);
                            } catch (\Exception $e) {
                                $errors++;

                                Log::error('Errore svuotamento tabella (bulk)', [
                                    'table' => $record->name,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }

                        DatabaseTable::clearCache();

                        Notification::make()
                            ->title('Operazione completata')
                            ->body("{$success} tabelle svuotate con successo" . ($errors > 0 ? ", {$errors} errori" : ''))
                            ->success()
                            ->send();
                    }),

                BulkAction::make('truncate_selected_with_fk')
                    ->label('Svuota Tabelle (disabilita FK)')
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Conferma Svuotamento Multiplo con FK Disabilitate')
                    ->modalDescription('Sei sicuro di voler svuotare tutte le tabelle selezionate disabilitando temporaneamente i controlli sulle chiavi esterne? Questa operazione eliminerà tutti i dati in modo permanente.')
                    ->modalSubmitActionLabel('Sì, svuota tabelle con FK disabilitate')
                    ->action(function (Collection $records) {
                        try {
                            static::rollbackFilamentTransaction();
                            static::disableForeignKeyChecks();

                            $success = 0;
                            $errors  = 0;

                            foreach ($records as $record) {
                                try {
                                    DB::table($record->name)->truncate();
                                    $success++;

                                    Log::info('Tabella svuotata (bulk con FK disabilitate)', [
                                        'table' => $record->name,
                                        'user'  => auth()->user()?->email ?? 'system',
                                    ]);
                                } catch (\Exception $e) {
                                    $errors++;

                                    Log::error('Errore svuotamento tabella (bulk con FK disabilitate)', [
                                        'table' => $record->name,
                                        'error' => $e->getMessage(),
                                    ]);
                                }
                            }

                            static::enableForeignKeyChecks();
                            DatabaseTable::clearCache();

                            Notification::make()
                                ->title('Operazione completata')
                                ->body("{$success} tabelle svuotate con successo" . ($errors > 0 ? ", {$errors} errori" : ''))
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            try {
                                static::enableForeignKeyChecks();
                            } catch (\Throwable) {
                            }

                            Log::error('Errore svuotamento bulk con FK disabilitate', [
                                'error' => $e->getMessage(),
                            ]);

                            Notification::make()
                                ->title('Errore durante lo svuotamento')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->headerActions([
                Action::make('create_table')
                    ->label('Crea Tabella')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->url(static::getUrl('create-table')),

                Action::make('sql_runner')
                    ->label('Query SQL')
                    ->icon('heroicon-o-command-line')
                    ->color('gray')
                    ->url(static::getUrl('sql')),
            ])
            ->emptyStateHeading('Nessuna tabella trovata')
            ->emptyStateDescription('Non ci sono tabelle disponibili nel database')
            ->defaultSort('rows', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'        => Pages\ListDatabaseTables::route('/'),
            'browse'       => Pages\BrowseTableRecords::route('/{record}/browse'),
            'sql'          => Pages\SqlQueryRunner::route('/sql'),
            'create-table' => Pages\CreateTable::route('/create-table'),
            'alter-table'  => Pages\AlterTable::route('/{record}/alter-table'),
        ];
    }
}

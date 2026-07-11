<?php

use Illuminate\Support\Facades\Schema;
use LucaPellegrino\DbMyAdmin\Models\DatabaseTable;
use LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource\Pages\ListDatabaseTables;
use LucaPellegrino\DbMyAdmin\Support\ConnectionManager;

beforeEach(function () {
    Schema::create('zebra', fn ($t) => $t->id());
    Schema::create('apple', fn ($t) => $t->id());
    Schema::create('mango', fn ($t) => $t->id());

    DatabaseTable::clearCache();

    // SQLite doesn't report real row counts, so inject distinct ones onto the cached
    // models. Distinct (non-tied) values are required to prove that the "rows"
    // tie-breaker sort Filament reapplies doesn't clobber the primary "name" sort.
    $rowCounts = ['zebra' => 3, 'apple' => 1, 'mango' => 5];
    DatabaseTable::getAllModels()->each(
        fn ($model) => $model->setAttribute('rows', $rowCounts[$model->name] ?? 0)
    );
});

afterEach(function () {
    Schema::dropIfExists('zebra');
    Schema::dropIfExists('apple');
    Schema::dropIfExists('mango');

    DatabaseTable::clearCache();
    ConnectionManager::reset();
});

function getListDatabaseTablesQuery(): Illuminate\Database\Eloquent\Builder
{
    $page = new ListDatabaseTables();
    $method = new ReflectionMethod($page, 'getTableQuery');
    $method->setAccessible(true);

    return $method->invoke($page);
}

it('sorts by name when a single orderBy is applied', function () {
    $names = getListDatabaseTablesQuery()
        ->orderBy('name', 'asc')
        ->get()
        ->pluck('name')
        ->values()
        ->all();

    expect($names)->toBe(['apple', 'mango', 'zebra']);
});

it('keeps the user-clicked column as the primary sort when Filament reapplies the defaultSort as a tie-breaker', function () {
    // Mirrors Filament\Tables\Concerns\CanSortRecords::applySortingToTableQuery(): it applies the
    // user-clicked column first, then re-applies the resource's ->defaultSort('rows', 'desc') as a
    // secondary tie-breaker. Since every table here has 0 rows (all tied), the tie-breaker must not
    // clobber the primary "name" sort.
    $names = getListDatabaseTablesQuery()
        ->orderBy('name', 'asc')
        ->orderBy('rows', 'desc')
        ->get()
        ->pluck('name')
        ->values()
        ->all();

    expect($names)->toBe(['apple', 'mango', 'zebra']);
});

it('sorts by name descending', function () {
    $names = getListDatabaseTablesQuery()
        ->orderBy('name', 'desc')
        ->orderBy('rows', 'desc')
        ->get()
        ->pluck('name')
        ->values()
        ->all();

    expect($names)->toBe(['zebra', 'mango', 'apple']);
});

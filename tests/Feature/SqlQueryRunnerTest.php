<?php

use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource\Pages\SqlQueryRunner;

beforeEach(function () {
    Schema::create('users', function ($t) {
        $t->id();
        $t->string('name');
        $t->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('users');
});

// ── runQuery: blocked statements ──────────────────────────────────────────────

it('sets errorMessage when running a blocked statement', function () {
    Livewire::test(SqlQueryRunner::class)
        ->set('query', 'DROP TABLE users')
        ->call('runQuery')
        ->assertSet('errorMessage', fn ($msg) => str_contains($msg, 'DROP'))
        ->assertSet('hasRun', true)
        ->assertSet('hasResults', false);
});

it('sets errorMessage for TRUNCATE', function () {
    Livewire::test(SqlQueryRunner::class)
        ->set('query', 'TRUNCATE TABLE users')
        ->call('runQuery')
        ->assertSet('errorMessage', fn ($msg) => str_contains($msg, 'TRUNCATE'));
});

it('sets errorMessage when query is empty', function () {
    Livewire::test(SqlQueryRunner::class)
        ->set('query', '')
        ->call('runQuery')
        ->assertSet('errorMessage', 'Inserisci una query SQL prima di eseguire.')
        ->assertSet('hasResults', false);
});

// ── runQuery: valid queries ───────────────────────────────────────────────────

it('executes a SELECT and populates results', function () {
    Livewire::test(SqlQueryRunner::class)
        ->set('query', 'SELECT 1 AS n')
        ->call('runQuery')
        ->assertSet('errorMessage', '')
        ->assertSet('hasRun', true)
        ->assertSet('hasResults', true)
        ->assertSet('totalRows', 1);
});

it('clears results after clearQuery', function () {
    Livewire::test(SqlQueryRunner::class)
        ->set('query', 'SELECT 1 AS n')
        ->call('runQuery')
        ->assertSet('hasRun', true)
        ->call('clearQuery')
        ->assertSet('query', '')
        ->assertSet('hasRun', false)
        ->assertSet('hasResults', false);
});

// ── Saved queries ─────────────────────────────────────────────────────────────

it('opens save modal', function () {
    Livewire::test(SqlQueryRunner::class)
        ->assertSet('showSaveModal', false)
        ->call('openSaveModal')
        ->assertSet('showSaveModal', true);
});

it('saves a query and shows it in savedQueries', function () {
    Livewire::test(SqlQueryRunner::class)
        ->set('query', 'SELECT * FROM users')
        ->call('openSaveModal')
        ->set('saveName', 'Lista utenti')
        ->call('saveQuery')
        ->assertSet('showSaveModal', false)
        ->assertSet('savedQueries', fn ($list) => count($list) === 1 && $list[0]['name'] === 'Lista utenti');
});

it('loads a saved query into the editor', function () {
    $component = Livewire::test(SqlQueryRunner::class)
        ->set('query', 'SELECT * FROM users')
        ->call('openSaveModal')
        ->set('saveName', 'Lista utenti')
        ->call('saveQuery');

    $id = $component->get('savedQueries')[0]['id'];

    $component
        ->set('query', '')
        ->call('useSavedQuery', $id)
        ->assertSet('query', 'SELECT * FROM users');
});

it('deletes a saved query', function () {
    $component = Livewire::test(SqlQueryRunner::class)
        ->set('query', 'SELECT * FROM users')
        ->call('openSaveModal')
        ->set('saveName', 'Lista utenti')
        ->call('saveQuery');

    expect($component->get('savedQueries'))->toHaveCount(1);

    $id = $component->get('savedQueries')[0]['id'];

    $component
        ->call('deleteSavedQuery', $id)
        ->assertSet('savedQueries', fn ($list) => count($list) === 0);
});

// ── Schema map ────────────────────────────────────────────────────────────────

it('builds schema map containing the test table', function () {
    Livewire::test(SqlQueryRunner::class)
        ->assertSet('schemaMap', fn ($map) => array_key_exists('users', $map));
});

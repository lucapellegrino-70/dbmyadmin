<?php

use LucaPellegrino\DbMyAdmin\Models\SavedQuery;

it('uses the configured table name', function () {
    expect((new SavedQuery())->getTable())->toBe('dbmyadmin_saved_queries');
});

it('truncates long SQL in preview', function () {
    $model      = new SavedQuery();
    $model->sql = str_repeat('SELECT * FROM users WHERE id = 1 AND name = ', 5);

    expect($model->sql_preview)->toHaveLength(80)
                               ->toEndWith('...');
});

it('returns full SQL when short', function () {
    $model      = new SavedQuery();
    $model->sql = 'SELECT 1';

    expect($model->sql_preview)->toBe('SELECT 1');
});

it('can be persisted and retrieved', function () {
    $query = SavedQuery::create([
        'name'       => 'Test Query',
        'sql'        => 'SELECT * FROM users',
        'created_by' => 'test@example.com',
    ]);

    expect($query->id)->toBeInt();
    expect(SavedQuery::find($query->id)->name)->toBe('Test Query');
});

<?php

use Illuminate\Support\Facades\Schema;
use LucaPellegrino\DbMyAdmin\Models\DynamicTableModel;
use LucaPellegrino\DbMyAdmin\Support\ConnectionManager;

beforeEach(function () {
    Schema::create('articles', function ($t) {
        $t->id();
        $t->string('title');
    });
});

afterEach(function () {
    Schema::dropIfExists('articles');
    ConnectionManager::reset();
});

it('sets the table name from constructor', function () {
    $model = new DynamicTableModel('articles');
    expect($model->getTable())->toBe('articles');
});

it('can query the dynamic table', function () {
    $model = new DynamicTableModel('articles');
    $count = $model->newQuery()->count();
    expect($count)->toBe(0);
});

it('uses config(dbmyadmin.connection) as the connection name', function () {
    config(['dbmyadmin.connection' => 'dbmyadmin_active']);

    $model = new DynamicTableModel('test_users');

    expect($model->getConnectionName())->toBe('dbmyadmin_active');

    config(['dbmyadmin.connection' => null]);
});

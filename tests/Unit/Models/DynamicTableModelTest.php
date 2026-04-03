<?php

use Illuminate\Support\Facades\Schema;
use LucaPellegrino\DbMyAdmin\Models\DynamicTableModel;

beforeEach(function () {
    Schema::create('articles', function ($t) {
        $t->id();
        $t->string('title');
    });
});

afterEach(function () {
    Schema::dropIfExists('articles');
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

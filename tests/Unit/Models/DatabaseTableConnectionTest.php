<?php

use LucaPellegrino\DbMyAdmin\Models\DatabaseTable;

afterEach(function () {
    config(['dbmyadmin.connection' => null]);
});

it('uses the app default connection name when dbmyadmin.connection is null', function () {
    $model = new DatabaseTable();

    expect($model->getConnectionName())->toBeNull();
});

it('uses config(dbmyadmin.connection) as the connection name when set', function () {
    config(['dbmyadmin.connection' => 'dbmyadmin_active']);

    $model = new DatabaseTable();

    expect($model->getConnectionName())->toBe('dbmyadmin_active');
});

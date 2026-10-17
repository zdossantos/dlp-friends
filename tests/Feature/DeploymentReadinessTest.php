<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('accepts a current schema without exposing connection details', function () {
    $this->artisan('deployment:check', ['--json' => true])
        ->expectsOutputToContain('"ready":true')
        ->assertSuccessful();
});

it('rejects a pending migration and closes application requests', function () {
    DB::table('migrations')->where('id', DB::table('migrations')->max('id'))->delete();
    app()->instance('env', 'production');

    $this->artisan('deployment:check', ['--json' => true])->assertFailed();
    $this->get('/fr')->assertServiceUnavailable();
    $this->get('/up')->assertOk();
});

it('rejects an absent migrations repository instead of treating it as current', function () {
    config()->set('database.migrations.table', 'deployment_missing_migrations');
    app()->forgetInstance('migration.repository');
    app()->forgetInstance('migrator');
    app()->instance('env', 'production');

    $this->artisan('deployment:check', ['--json' => true])->assertFailed();
    $this->get('/fr')->assertServiceUnavailable();
});

it('rejects an unavailable database but keeps the process health endpoint usable', function () {
    config()->set('database.connections.unreachable', array_merge(config('database.connections.mysql'), [
        'host' => '127.0.0.1', 'port' => 9, 'password' => 'private-test-password',
    ]));
    DB::setDefaultConnection('unreachable');
    app()->instance('env', 'production');

    $this->artisan('deployment:check', ['--json' => true])->assertFailed();
    $this->get('/fr')->assertServiceUnavailable()->assertDontSee('private-test-password');
    $this->get('/up')->assertOk();
    DB::setDefaultConnection('mysql');
});

it('requires explicit permission to check a closed deployment', function () {
    $this->artisan('down')->assertSuccessful();
    try {
        $this->artisan('deployment:check')->assertFailed();
        $this->artisan('deployment:check', ['--allow-maintenance' => true])->assertSuccessful();
    } finally {
        $this->artisan('up')->assertSuccessful();
    }
});

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
    $this->getJson('/ready')->assertServiceUnavailable()->assertExactJson(['ready' => false]);
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

it('rejects a queue reservation that can expire before the production worker stops', function (int $retryAfter) {
    config()->set('queue.default', 'redis');
    config()->set('queue.connections.redis.retry_after', $retryAfter);

    $this->artisan('deployment:check', ['--json' => true])
        ->expectsOutputToContain('unsafe_queue_reservation')
        ->assertFailed();
})->with([60, 90]);

it('keeps a thirty second margin between the worker timeout and queue reservation', function () {
    expect(config('queue.connections.redis.retry_after'))->toBeGreaterThanOrEqual(120);
    $this->artisan('deployment:check', ['--json' => true])->assertSuccessful();
});

it('exposes readiness without a session or cached success', function () {
    $this->getJson('/ready')->assertOk()->assertExactJson(['ready' => true])
        ->assertHeader('Cache-Control', 'no-store, private')->assertCookieMissing(config('session.cookie'));
});

it('keeps liveness healthy but refuses readiness with a pending migration', function () {
    DB::table('migrations')->where('id', DB::table('migrations')->max('id'))->delete();
    app()->instance('env', 'production');
    $this->getJson('/ready')->assertServiceUnavailable()->assertExactJson(['ready' => false]);
    $this->get('/up')->assertOk();
});

it('refuses readiness during maintenance without disclosing the bypass', function () {
    $this->artisan('down', ['--secret' => 'operator-only'])->assertSuccessful();
    try {
        $this->getJson('/ready')->assertServiceUnavailable()->assertExactJson(['ready' => false])
            ->assertDontSee('operator-only');
    } finally {
        $this->artisan('up')->assertSuccessful();
    }
});

it('refuses readiness when the configured cache cannot be read', function () {
    config()->set('cache.default', 'redis');
    config()->set('database.redis.cache.port', 9);
    config()->set('database.redis.cache.read_timeout', 1);
    config()->set('database.redis.cache.timeout', 1);
    $this->getJson('/ready')->assertServiceUnavailable()->assertExactJson(['ready' => false]);
    $this->get('/up')->assertOk();
});

it('checks the session Redis even when cache uses a different backend', function () {
    config()->set('session.driver', 'redis');
    config()->set('session.connection', 'unreachable_sessions');
    config()->set('database.redis.unreachable_sessions', [
        'host' => '127.0.0.1', 'port' => 9, 'timeout' => 1, 'read_timeout' => 1,
    ]);
    $this->getJson('/ready')->assertServiceUnavailable()->assertExactJson(['ready' => false]);
});

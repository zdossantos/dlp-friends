<?php

use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->credentials = tempnam(sys_get_temp_dir(), 'beta-admin-');
    file_put_contents($this->credentials, json_encode(['email' => 'beta-admin@example.test', 'password' => str_repeat('random-private-', 5)]));
    chmod($this->credentials, 0600);
    $this->app->detectEnvironment(fn () => 'beta');
    $this->mock(MaintenanceMode::class)->shouldReceive('active')->andReturn(true);
    foreach ([RoleName::User, RoleName::Admin] as $role) {
        Role::query()->firstOrCreate(['name' => $role]);
    }
});

afterEach(fn () => unlink($this->credentials));

it('bootstraps only a verified dedicated beta administrator without sending mail', function () {
    Mail::fake();
    $this->artisan('beta:bootstrap-admin', ['--credentials' => $this->credentials, '--force' => true])->assertSuccessful();
    $user = User::query()->sole();
    expect($user->email)->toBe('beta-admin@example.test')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->hasRole(RoleName::Admin))->toBeTrue()
        ->and($user->hasRole(RoleName::User))->toBeTrue()
        ->and(Hash::check(str_repeat('random-private-', 5), $user->password))->toBeTrue()
        ->and($user->profile)->toBeNull();
    Mail::assertNothingSent();
});

it('refuses to bootstrap over existing beta members', function () {
    User::factory()->create();
    $this->artisan('beta:bootstrap-admin', ['--credentials' => $this->credentials, '--force' => true])->assertFailed();
    expect(User::query()->count())->toBe(1);
});

it('refuses unsafe bootstrap contexts and credential files', function (string $scenario) {
    if ($scenario === 'production') {
        $this->app->detectEnvironment(fn () => 'production');
    } elseif ($scenario === 'public-file') {
        chmod($this->credentials, 0644);
    } elseif ($scenario === 'invalid-credentials') {
        file_put_contents($this->credentials, json_encode(['email' => 'invalid', 'password' => 'short']));
    } elseif ($scenario === 'open-beta') {
        $this->mock(MaintenanceMode::class)->shouldReceive('active')->andReturn(false);
    }
    $this->artisan('beta:bootstrap-admin', ['--credentials' => $this->credentials, '--force' => $scenario !== 'no-confirmation'])->assertFailed();
    expect(User::query()->count())->toBe(0);
})->with(['production', 'public-file', 'invalid-credentials', 'open-beta', 'no-confirmation']);

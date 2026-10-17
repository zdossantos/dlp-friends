<?php

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('requires beta maintenance and an explicit replacement confirmation', function (string $environment, bool $maintenance, bool $force) {
    app()->instance('env', $environment);
    if ($maintenance) {
        app()->maintenanceMode()->activate([]);
    }
    $user = User::factory()->create(['remember_token' => 'preserve-on-refusal']);

    try {
        $this->artisan('beta:sanitize-copy', $force ? ['--force' => true] : [])->assertFailed();
        expect($user->fresh()->remember_token)->toBe('preserve-on-refusal');
    } finally {
        app()->maintenanceMode()->deactivate();
    }
})->with([
    'production' => ['production', true, true],
    'open beta' => ['beta', false, true],
    'no confirmation' => ['beta', true, false],
]);

it('preserves profiles messages and files but removes delivery credentials without decrypting them', function () {
    $user = User::factory()->withProfile()->create();
    $message = Message::factory()->create();
    DB::table('users')->where('id', $user->id)->update([
        'remember_token' => 'production-token',
        'two_factor_secret' => 'encrypted-with-another-key',
        'two_factor_recovery_codes' => 'encrypted-with-another-key',
        'two_factor_confirmed_at' => now(),
    ]);
    DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => 'production-token']);
    DB::table('passkeys')->insert(['user_id' => $user->id, 'name' => 'Production', 'credential_id' => 'production-credential', 'credential' => '{}']);
    DB::table('social_accounts')->insert(['user_id' => $user->id, 'provider' => 'google', 'provider_user_id' => 'production-oauth-id']);
    DB::table('sessions')->insert(['id' => 'production-session', 'user_id' => $user->id, 'payload' => 'private', 'last_activity' => time()]);
    DB::table('web_push_subscriptions')->insert([
        'uuid' => (string) Str::uuid(), 'user_id' => $user->id,
        'endpoint' => 'encrypted-with-another-key', 'endpoint_hash' => str_repeat('a', 64),
        'p256dh' => 'encrypted-with-another-key', 'auth' => 'encrypted-with-another-key',
    ]);
    DB::table('jobs')->insert(['queue' => 'default', 'payload' => 'private', 'attempts' => 0, 'available_at' => time(), 'created_at' => time()]);
    $profile = DB::table('profiles')->where('user_id', $user->id)->first();
    $originalMessage = DB::table('messages')->where('id', $message->id)->first();
    $password = $user->password;
    app()->instance('env', 'beta');
    app()->maintenanceMode()->activate([]);

    try {
        $this->artisan('beta:sanitize-copy', ['--force' => true])->assertSuccessful();
        foreach (['password_reset_tokens', 'sessions', 'passkeys', 'social_accounts', 'web_push_subscriptions', 'jobs'] as $table) {
            expect(DB::table($table)->count())->toBe(0);
        }
        expect(DB::table('profiles')->where('user_id', $user->id)->first())->toEqual($profile)
            ->and(DB::table('messages')->where('id', $message->id)->first())->toEqual($originalMessage)
            ->and($user->fresh()->password)->toBe($password)
            ->and($user->fresh()->remember_token)->toBeNull()
            ->and($user->fresh()->two_factor_secret)->toBeNull()
            ->and($user->fresh()->two_factor_recovery_codes)->toBeNull()
            ->and($user->fresh()->two_factor_confirmed_at)->toBeNull();
    } finally {
        app()->maintenanceMode()->deactivate();
    }
});

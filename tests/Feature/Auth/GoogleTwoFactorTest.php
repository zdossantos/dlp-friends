<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

test('linked Google identities must complete local two factor before login or disclosure of a ban', function (string $status) {
    $user = User::factory()->withTwoFactor()->create(['status' => $status, 'session_version' => 4]);
    $user->socialAccounts()->create(['provider' => 'google', 'provider_user_id' => 'linked-2fa']);
    Socialite::fake('google', (new SocialiteUser)->map(['id' => 'linked-2fa', 'email' => $user->email]));

    $this->get('/auth/google/callback')->assertRedirect(route('two-factor.login'))
        ->assertSessionHas('login.id', $user->id)->assertSessionMissing('auth_session_version');
    $this->assertGuest();
    $this->from('/two-factor-challenge')->post('/two-factor-challenge', ['recovery_code' => 'invalid'])->assertSessionHasErrors();
    expect(json_encode(session('errors')->getBag('default')->getMessages()))->not->toContain(__('moderation.banned'));
    $this->assertGuest();
    $response = $this->post('/two-factor-challenge', ['recovery_code' => 'recovery-code-1']);
    if ($status === 'banned') {
        $response->assertRedirect(route('login'))->assertSessionHasErrors('email');
        expect(session('errors')->first('email'))->toContain(__('moderation.banned'));
        $this->assertGuest();
    } else {
        $response->assertRedirect('/app')->assertSessionHas('auth_session_version', 4);
        $this->assertAuthenticatedAs($user);
    }
})->with(['active', 'banned']);

test('a pending deletion account cannot start a Google two factor challenge', function () {
    $user = User::factory()->withTwoFactor()->create(['status' => 'pending_deletion']);
    $user->socialAccounts()->create(['provider' => 'google', 'provider_user_id' => 'pending-2fa']);
    Socialite::fake('google', (new SocialiteUser)->map(['id' => 'pending-2fa', 'email' => $user->email]));
    $this->get('/auth/google/callback')->assertRedirect(route('login'))->assertSessionHasErrors('social_auth')->assertSessionMissing('login.id');
    $this->assertGuest();
});

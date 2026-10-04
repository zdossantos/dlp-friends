<?php

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

use Inertia\Testing\AssertableInertia as Assert;

it('guards private package routes and realtime authorization until current terms are accepted', function (string $method, string $url) {
    $user = User::factory()->withTwoFactor()->create();
    $user->termsAcceptances()->delete();
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
    $payload = ['channel_name' => "private-App.Models.User.{$user->id}", 'socket_id' => '1.2'];
    $this->{$method}($url, $payload)->assertRedirect(route('terms.acceptance.show'));
    $this->assertDatabaseCount('passkeys', 0);
})->with([['post', '/broadcasting/auth'], ['get', '/user/confirmed-password-status'], ['get', '/user/passkeys/options'], ['get', '/user/two-factor-recovery-codes']]);

it('refuses banned sessions on private package routes', function (string $url) {
    $user = User::factory()->withTwoFactor()->create(['status' => 'banned']);
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->get($url)->assertForbidden();
    $this->assertGuest();
})->with(['/user/confirmed-password-status', '/user/passkeys/options', '/user/two-factor-recovery-codes']);

it('requires explicit current terms acceptance for every active role', function (string $role, string $url) {
    $user = User::factory()->withProfile()->{$role}()->create();
    $user->termsAcceptances()->delete();
    $user->termsAcceptances()->create(['terms_version' => '2026-09-01', 'accepted_at' => now()->subMonth()]);
    $this->actingAs($user)->get($url)->assertRedirect(route('terms.acceptance.show'));
    $this->get(route('terms.acceptance.show'))->assertInertia(fn (Assert $page) => $page->component('auth/AcceptTerms')->where('version', '2026-10-04'));
    $this->post(route('terms.acceptance.store'), ['accepted' => false])->assertSessionHasErrors('accepted');
    $this->get($url)->assertRedirect(route('terms.acceptance.show'));
    $this->post(route('terms.acceptance.store'), ['accepted' => true, 'terms_version' => 'forged'])->assertRedirect();
    expect($user->termsAcceptances()->where('terms_version', '2026-10-04')->count())->toBe(1);
    $this->get($url)->assertOk();
    $this->post(route('terms.acceptance.store'), ['accepted' => true])->assertRedirect();
    expect($user->termsAcceptances()->where('terms_version', '2026-10-04')->count())->toBe(1);
})->with([['withTwoFactor', '/conversations'], ['admin', '/dashboard'], ['partnerOnly', '/partner/profile']]);

it('does not let a banned member accept terms to recover private access', function () {
    $user = User::factory()->create(['status' => UserStatus::Banned]);
    $user->termsAcceptances()->delete();
    $this->actingAs($user)->post('/terms/accept', ['accepted' => true])->assertForbidden();
    expect($user->termsAcceptances()->count())->toBe(0);
    $this->assertGuest();
    $this->get('/fr/conditions-generales-utilisation')->assertOk();
});

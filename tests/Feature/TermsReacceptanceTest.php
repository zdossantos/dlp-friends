<?php

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

use Inertia\Testing\AssertableInertia as Assert;

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

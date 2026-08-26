<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a visitor stores an explicit locale in a persistent cookie', function () {
    $this->patch(route('locale.update'), ['locale' => 'en'])
        ->assertRedirect()
        ->assertCookie('locale', 'en');
});

test('a member choice is stored on the account and takes priority on another browser', function () {
    $user = User::factory()->create(['locale' => null]);

    $this->actingAs($user)
        ->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')
        ->patch(route('locale.update'), ['locale' => 'en'])
        ->assertRedirect()
        ->assertCookie('locale', 'en');

    expect($user->fresh()->locale)->toBe('en');

    $this->actingAs($user->fresh())
        ->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')
        ->get('/')
        ->assertOk();

    expect(app()->getLocale())->toBe('en');
});

test('a locale preference only accepts supported locales', function () {
    $this->patch(route('locale.update'), ['locale' => 'de'])
        ->assertSessionHasErrors('locale');
});

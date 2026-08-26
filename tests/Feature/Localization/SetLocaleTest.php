<?php

use App\Models\Interest;
use App\Models\User;

test('the account locale takes priority over the visitor cookie and browser language', function () {
    $user = User::factory()->create(['locale' => 'fr']);

    $this->actingAs($user)
        ->withCookie('locale', 'en')
        ->withHeader('Accept-Language', 'en-GB,en;q=0.9')
        ->get('/')
        ->assertOk();

    expect(app()->getLocale())->toBe('fr');
});

test('the visitor cookie takes priority over the browser language', function () {
    $this->withCookie('locale', 'en')
        ->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')
        ->get('/')
        ->assertOk();

    expect(app()->getLocale())->toBe('en');
});

test('the browser language selects a supported primary language tag', function () {
    $this->withHeader('Accept-Language', 'en-US,en;q=0.9')
        ->get('/')
        ->assertOk();

    expect(app()->getLocale())->toBe('en');
});

test('an unsupported browser language falls back to French', function () {
    $this->withHeader('Accept-Language', 'de-DE,de;q=0.9')
        ->get('/')
        ->assertOk();

    expect(app()->getLocale())->toBe('fr');
});

test('an English interest falls back to its French name when untranslated', function () {
    app()->setLocale('en');
    $interest = Interest::factory()->create([
        'name' => 'Parades de test',
        'name_en' => null,
    ]);

    expect($interest->display_name)->toBe('Parades de test');
});

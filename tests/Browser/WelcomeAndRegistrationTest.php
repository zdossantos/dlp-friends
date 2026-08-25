<?php

use App\Models\User;

test('the landing page presents the adult friendship service to guests', function () {
    visit('/')
        ->assertSee('DLP Friends')
        ->assertSee('Des rencontres strictement amicales entre fans adultes')
        ->assertSeeLink('Créer mon compte')
        ->assertSeeLink('Se connecter')
        ->assertNoJavaScriptErrors();
});

test('the landing page offers the member space when signed in', function () {
    $this->actingAs(User::factory()->withProfile()->create());

    visit('/')
        ->assertSeeLink('Ouvrir mon espace')
        ->assertDontSeeLink('Créer mon compte');
});

test('the registration form collects account data without a public name', function () {
    visit('/register')
        ->assertPresent('input[name="email"]')
        ->assertPresent('input[name="birth_date"]')
        ->assertPresent('input[name="password"]')
        ->assertPresent('input[name="password_confirmation"]')
        ->assertNotPresent('input[name="username"]');
});

test('the auth card exposes brand theme control and form landmark', function () {
    visit('/login')
        ->assertSee('DLP Friends')
        ->assertPresent('#contenu-principal')
        ->assertPresent('form[action]')
        ->assertPresent('[aria-label="Choisir le thème"]')
        ->assertNoAccessibilityIssues();
});

test('the landing header stacks on a mobile viewport', function () {
    visit('/')
        ->on()->mobile()
        ->assertScript(
            "getComputedStyle(document.querySelector('header')).flexDirection",
            'column',
        );
});

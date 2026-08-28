<?php

use App\Models\User;

test('the landing page presents the adult friendship service to guests', function () {
    visit('/', ['locale' => 'fr-FR'])
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
    visit('/register', ['locale' => 'fr-FR'])
        ->assertSee('Vous créerez ensuite votre profil, puis un tutoriel vous expliquera comment rencontrer d’autres membres.')
        ->assertPresent('input[name="email"]')
        ->assertPresent('input[name="birth_date"]')
        ->assertPresent('input[name="password"]')
        ->assertPresent('input[name="password_confirmation"]')
        ->assertNotPresent('input[name="username"]');
});

test('public and authentication pages expose language without theme controls', function () {
    visit('/', ['locale' => 'fr-FR'])
        ->assertPresent('[data-test="locale-switcher"]')
        ->assertNotPresent('[aria-label="Choisir le thème"]');

    visit('/login', ['locale' => 'fr-FR'])
        ->assertSee('DLP Friends')
        ->assertPresent('#contenu-principal')
        ->assertPresent('form[action]')
        ->assertPresent('[data-test="locale-switcher"]')
        ->assertNotPresent('[aria-label="Choisir le thème"]')
        ->assertNoAccessibilityIssues();
});

test('the landing header stacks on a mobile viewport', function () {
    visit('/', ['locale' => 'fr-FR'])
        ->on()->mobile()
        ->assertScript(
            "getComputedStyle(document.querySelector('header')).flexDirection",
            'column',
        );
});

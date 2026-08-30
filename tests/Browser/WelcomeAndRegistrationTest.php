<?php

use App\Models\User;

test('the landing page presents the adult friendship service to guests', function () {
    visit('/', ['locale' => 'fr-FR'])
        ->assertSee('DLP Friends')
        ->assertPresent('[data-test="app-logo-icon"]')
        ->assertAttribute('[data-test="app-logo-icon"]', 'aria-hidden', 'true')
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
        ->assertPresent('[data-test="app-logo-icon"]')
        ->assertAttribute('[data-test="app-logo-icon"]', 'aria-hidden', 'true')
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

test('the landing page uses a bold decorative typeface for editorial content', function () {
    visit('/', ['locale' => 'fr-FR'])
        ->assertScript(
            "getComputedStyle(document.querySelector('main h1')).fontFamily.includes('Cinzel Decorative')",
            true,
        )
        ->assertScript(
            "getComputedStyle(document.querySelector('header .font-accent')).fontWeight",
            '700',
        )
        ->assertScript(
            "getComputedStyle(document.querySelector('main h1')).fontWeight",
            '700',
        )
        ->assertScript(
            "getComputedStyle(document.querySelector('main a')).fontFamily.includes('Instrument Sans')",
            true,
        );
});

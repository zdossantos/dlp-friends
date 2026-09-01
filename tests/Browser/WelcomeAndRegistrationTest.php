<?php

use App\Models\User;

test('the landing page presents the adult friendship service to guests', function () {
    visit('/fr', ['locale' => 'fr-FR'])
        ->assertSee('DLP Friends')
        ->assertPresent('[data-test="app-logo-icon"]')
        ->assertAttribute('[data-test="app-logo-icon"]', 'aria-hidden', 'true')
        ->assertSee('Vis la magie à plusieurs')
        ->assertSee('Rencontre d’autres fans de Disneyland Paris, découvre vos passions communes et échange simplement.')
        ->assertScript("document.body.innerText.includes('Vos passions communes passent en premier')", true)
        ->assertScript("document.body.innerText.includes('Ton âge n’est jamais pris en compte par l’algorithme pour te proposer des profils.')", true)
        ->assertSeeLink('Créer mon compte')
        ->assertSeeLink('Se connecter')
        ->assertAttribute('[data-test="landing-register"]', 'href', '/register')
        ->assertAttribute('[data-test="landing-login"]', 'href', '/login')
        ->assertScript("document.querySelector('link[rel=canonical]').href.endsWith('/fr')", true)
        ->assertNoAccessibilityIssues()
        ->assertNoJavaScriptErrors();
});

test('the landing page sends a signed-in member directly to discovery', function () {
    $this->actingAs(User::factory()->withProfile()->create());

    visit('/')
        ->assertPathIs('/discover');
});

test('the registration form collects account data without a public name', function () {
    visit('/register', ['locale' => 'fr-FR'])
        ->assertSee('Tu créeras ensuite ton profil, puis un tutoriel te montrera comment découvrir d’autres membres.')
        ->assertPresent('input[name="email"]')
        ->assertPresent('input[name="birth_date"]')
        ->assertPresent('input[name="password"]')
        ->assertPresent('input[name="password_confirmation"]')
        ->assertNotPresent('input[name="username"]');
});

test('public and authentication pages expose language without theme controls', function () {
    visit('/fr', ['locale' => 'fr-FR'])
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
    visit('/fr', ['locale' => 'fr-FR'])
        ->on()->mobile()
        ->assertScript(
            "getComputedStyle(document.querySelector('header')).flexDirection",
            'column',
        );
});

test('the landing benefits use distinct decorative icons', function () {
    visit('/fr', ['locale' => 'fr-FR'])
        ->assertPresent('[data-test="landing-benefit-icon-interests"] svg[data-icon="shapes"]')
        ->assertPresent('[data-test="landing-benefit-icon-discovery"] svg[data-icon="handshake"]')
        ->assertPresent('[data-test="landing-benefit-icon-conversations"] svg[data-icon="message-circle"]')
        ->assertAttribute('[data-test="landing-benefit-icon-interests"] svg', 'aria-hidden', 'true')
        ->assertAttribute('[data-test="landing-benefit-icon-discovery"] svg', 'aria-hidden', 'true')
        ->assertAttribute('[data-test="landing-benefit-icon-conversations"] svg', 'aria-hidden', 'true');
});

test('the landing page uses a bold decorative typeface for editorial content', function () {
    visit('/fr', ['locale' => 'fr-FR'])
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

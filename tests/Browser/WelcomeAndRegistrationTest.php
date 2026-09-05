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
        ->assertSeeLink('Comprendre nos suggestions')
        ->assertAttribute('[data-test="landing-matching"]', 'href', '/fr/matching')
        ->assertScript("document.querySelector('[data-test=legal-terms]').href.endsWith('/fr/conditions-generales-utilisation')", true)
        ->assertScript("document.querySelector('[data-test=legal-privacy]').href.endsWith('/fr/politique-confidentialite')", true)
        ->assertScript("document.querySelector('link[rel=canonical]').href.endsWith('/fr')", true)
        ->assertNoAccessibilityIssues()
        ->assertNoJavaScriptErrors();
});

test('the landing matching link opens an accessible localized explanation', function () {
    visit('/fr', ['locale' => 'fr-FR'])
        ->click('[data-test="landing-matching"]')
        ->assertPathIs('/fr/matching')
        ->assertSee('Comment fonctionnent les suggestions')
        ->assertSee('Deux Découvertes créent un Univers croisé')
        ->assertPresent('[data-test="matching-document-card"]')
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
        ->assertPresent('#terms_accepted')
        ->assertAttribute('#terms_accepted', 'data-state', 'unchecked')
        ->assertSeeLink('Conditions générales d’utilisation')
        ->assertSeeLink('Politique de confidentialité')
        ->assertNotPresent('input[name="username"]');
});

test('login and registration offer only Google social authentication', function () {
    foreach (['/login', '/register'] as $path) {
        visit($path, ['locale' => 'fr-FR'])
            ->assertCount('[data-test="social-google"]', 1)
            ->assertNotPresent('[data-test="social-apple"]')
            ->assertSeeLink('Google')
            ->assertDontSeeLink('Apple')
            ->assertScript(
                "document.querySelector('[data-test=\"social-google\"]')?.getAttribute('href')?.endsWith('/auth/google/redirect')",
                true,
            )
            ->assertAttribute('[data-test="social-google"] svg', 'aria-hidden', 'true');
    }
});

test('registration submits an explicit terms choice', function () {
    visit('/register', ['locale' => 'fr-FR'])
        ->fill('email', 'browser-terms@example.com')
        ->fill('birth_date', '2000-01-01')
        ->fill('password', 'password')
        ->fill('password_confirmation', 'password')
        ->click('#terms_accepted')
        ->click('[data-test="register-user-button"]')
        ->assertPathIs('/email/verify');
});

test('localized legal pages are responsive and accessible', function () {
    visit('/fr/conditions-generales-utilisation', ['locale' => 'fr-FR'])
        ->assertSee('Conditions générales d’utilisation')
        ->assertPresent('[data-test="app-logo-icon"]')
        ->assertPresent('[data-test="legal-document-card"]')
        ->assertNoAccessibilityIssues()
        ->assertNoJavaScriptErrors();

    visit('/en/privacy-policy', ['locale' => 'en-GB'])
        ->assertSee('Privacy Policy')
        ->assertNoAccessibilityIssues()
        ->assertNoJavaScriptErrors();
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

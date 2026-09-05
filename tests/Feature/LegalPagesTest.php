<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('localized legal documents are public server rendered and indexable', function (string $path, string $locale, string $heading) {
    config()->set('app.url', 'https://dlp-friends.example');
    config()->set('legal.contact_email', 'legal@dlp-friends.example');

    $this->get($path)
        ->assertOk()
        ->assertViewIs('legal.show')
        ->assertHeaderMissing('X-Robots-Tag')
        ->assertSee('<html lang="'.$locale.'">', false)
        ->assertSee('<main', false)
        ->assertSee($heading)
        ->assertSee('legal@dlp-friends.example')
        ->assertDontSee('type="module"', false);
})->with([
    'French terms' => ['/fr/conditions-generales-utilisation', 'fr', 'Conditions générales d’utilisation'],
    'English terms' => ['/en/terms-of-use', 'en', 'Terms of Use'],
    'French privacy' => ['/fr/politique-confidentialite', 'fr', 'Politique de confidentialité'],
    'English privacy' => ['/en/privacy-policy', 'en', 'Privacy Policy'],
]);

test('privacy pages disclose hosting and backup retention', function () {
    config()->set('legal.contact_email', 'legal@dlp-friends.example');

    $this->get('/fr/politique-confidentialite')
        ->assertOk()
        ->assertSee('IONOS')
        ->assertSee('30 jours');
});

test('privacy pages disclose google analytics audience measurement', function () {
    config()->set('legal.contact_email', 'legal@dlp-friends.example');

    $this->get('/fr/politique-confidentialite')
        ->assertOk()
        ->assertSee('Google Analytics 4')
        ->assertSee('mesure d’audience');

    $this->get('/en/privacy-policy')
        ->assertOk()
        ->assertSee('Google Analytics 4')
        ->assertSee('audience measurement');
});

test('legal navigation keeps the current browser host', function () {
    config()->set('app.url', 'http://localhost');
    config()->set('legal.contact_email', 'legal@dlp-friends.example');

    $this->withHeader('Host', 'friends.test:8123')
        ->get('/fr/conditions-generales-utilisation')
        ->assertOk()
        ->assertSee('href="/fr"', false)
        ->assertSee('href="/en/terms-of-use"', false)
        ->assertDontSee('<a href="http://localhost', false);
});

test('legal pages reuse the public application visual language', function () {
    config()->set('legal.contact_email', 'legal@dlp-friends.example');

    $this->get('/fr/politique-confidentialite')
        ->assertOk()
        ->assertSee('data-test="app-logo-icon"', false)
        ->assertSee('data-test="legal-document-card"', false)
        ->assertSee('font-accent', false)
        ->assertSee('bg-background', false);
});

test('production legal pages require a public contact email', function () {
    app()->detectEnvironment(fn (): string => 'production');
    config()->set('legal.contact_email');

    $this->withoutExceptionHandling()->get('/fr/politique-confidentialite');
})->throws(RuntimeException::class, 'LEGAL_CONTACT_EMAIL');

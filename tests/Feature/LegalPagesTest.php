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

test('production legal pages require a public contact email', function () {
    app()->detectEnvironment(fn (): string => 'production');
    config()->set('legal.contact_email');

    $this->withoutExceptionHandling()->get('/fr/politique-confidentialite');
})->throws(RuntimeException::class, 'LEGAL_CONTACT_EMAIL');

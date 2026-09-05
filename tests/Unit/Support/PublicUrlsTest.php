<?php

use App\Support\PublicUrls;
use Tests\TestCase;

uses(TestCase::class);

test('legal public urls use stable localized slugs', function () {
    config()->set('app.url', 'https://dlp-friends.example');

    expect(PublicUrls::terms('fr'))->toBe('https://dlp-friends.example/fr/conditions-generales-utilisation')
        ->and(PublicUrls::terms('en'))->toBe('https://dlp-friends.example/en/terms-of-use')
        ->and(PublicUrls::privacy('fr'))->toBe('https://dlp-friends.example/fr/politique-confidentialite')
        ->and(PublicUrls::privacy('en'))->toBe('https://dlp-friends.example/en/privacy-policy')
        ->and(PublicUrls::termsPath('fr'))->toBe('/fr/conditions-generales-utilisation')
        ->and(PublicUrls::termsPath('en'))->toBe('/en/terms-of-use')
        ->and(PublicUrls::privacyPath('fr'))->toBe('/fr/politique-confidentialite')
        ->and(PublicUrls::privacyPath('en'))->toBe('/en/privacy-policy');
});

test('matching public urls use stable localized paths', function () {
    config()->set('app.url', 'https://dlp-friends.example');

    expect(PublicUrls::matching('fr'))->toBe('https://dlp-friends.example/fr/matching')
        ->and(PublicUrls::matching('en'))->toBe('https://dlp-friends.example/en/matching')
        ->and(PublicUrls::matchingPath('fr'))->toBe('/fr/matching')
        ->and(PublicUrls::matchingPath('en'))->toBe('/en/matching');
});

<?php

use App\Support\PublicUrls;
use Tests\TestCase;

uses(TestCase::class);

test('legal public urls use stable localized slugs', function () {
    config()->set('app.url', 'https://dlp-friends.example');

    expect(PublicUrls::terms('fr'))->toBe('https://dlp-friends.example/fr/conditions-generales-utilisation')
        ->and(PublicUrls::terms('en'))->toBe('https://dlp-friends.example/en/terms-of-use')
        ->and(PublicUrls::privacy('fr'))->toBe('https://dlp-friends.example/fr/politique-confidentialite')
        ->and(PublicUrls::privacy('en'))->toBe('https://dlp-friends.example/en/privacy-policy');
});

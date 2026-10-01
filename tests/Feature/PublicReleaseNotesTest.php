<?php

use App\Support\PublicReleaseNotes;

test('localized release notes render only feature releases in newest first order', function (string $locale, string $path, string $heading) {
    config()->set('app.url', 'https://dlp-friends.example');
    config()->set('release-notes.items', [
        [
            'version' => '1.9.0',
            'date' => '2026-09-28',
            'fr' => ['Ancienne fonctionnalité'],
            'en' => ['Older feature'],
        ],
        [
            'version' => '1.11.0',
            'date' => '2026-09-30',
            'fr' => ['Nouvelle fonctionnalité'],
            'en' => ['Newer feature'],
        ],
    ]);

    $response = $this->get($path)
        ->assertOk()
        ->assertHeaderMissing('X-Robots-Tag')
        ->assertViewIs('release-notes.index')
        ->assertSee($heading)
        ->assertSeeInOrder(['1.11.0', '1.9.0'])
        ->assertSee($locale === 'fr' ? 'Nouvelle fonctionnalité' : 'Newer feature')
        ->assertDontSee($locale === 'fr' ? 'Newer feature' : 'Nouvelle fonctionnalité');

    $response->assertSee('<link rel="canonical" href="https://dlp-friends.example'.$path.'">', false)
        ->assertSee('hreflang="fr" href="https://dlp-friends.example/fr/nouveautes"', false)
        ->assertSee('hreflang="en" href="https://dlp-friends.example/en/whats-new"', false);
})->with([
    'French' => ['fr', '/fr/nouveautes', 'Nouveautés de DLP Friends'],
    'English' => ['en', '/en/whats-new', 'What’s new in DLP Friends'],
]);

test('release note content is escaped and release links are generated from validated versions', function () {
    config()->set('release-notes.items', [[
        'version' => '1.11.0',
        'date' => '2026-09-30',
        'fr' => ['<script>alert("notes")</script> [lien](javascript:alert(1))'],
        'en' => ['Safe note'],
    ]]);

    $this->get('/fr/nouveautes')
        ->assertOk()
        ->assertSee('&lt;script&gt;alert(&quot;notes&quot;)&lt;/script&gt; [lien](javascript:alert(1))', false)
        ->assertDontSee('<script>alert("notes")</script>', false)
        ->assertSee('href="https://github.com/zdossantos/dlp-friends/releases/tag/v1.11.0"', false)
        ->assertDontSee('href="javascript:', false);
});

test('versioned notes cover every changelog release containing features and ignore fixes only releases', function () {
    $notes = app(PublicReleaseNotes::class);
    $fixture = <<<'MARKDOWN'
# Changelog

## [2.1.0](https://example.test/v2.1.0) (2026-10-02)

### Features

* add something

### Fixes

* fix something

## [2.0.1](https://example.test/v2.0.1) (2026-10-01)

### Fixes

* patch something
MARKDOWN;

    expect($notes->featureVersions($fixture))->toBe(['2.1.0']);

    $publishedFeatureVersions = $notes->featureVersions(file_get_contents(base_path('CHANGELOG.md')));
    $documentedVersions = collect(config('release-notes.items'))->pluck('version')->all();

    expect($documentedVersions)->toBe($publishedFeatureVersions);
    expect(collect(config('release-notes.items'))->every(
        fn (array $item): bool => ($item['fr'] ?? []) !== [] && ($item['en'] ?? []) !== [],
    ))->toBeTrue();
});

test('a feature release without both translations is rejected', function () {
    config()->set('release-notes.items', [[
        'version' => '2.1.0',
        'date' => '2026-10-02',
        'fr' => ['Une fonctionnalité'],
        'en' => [],
    ]]);

    expect(fn () => app(PublicReleaseNotes::class)->items('fr'))
        ->toThrow(LogicException::class);
});

test('the public navigation and sitemap expose both localized release note pages', function (string $locale, string $path) {
    $this->get('/'.$locale)
        ->assertOk()
        ->assertSee('href="'.$path.'"', false);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('/fr/nouveautes', false)
        ->assertSee('/en/whats-new', false);
})->with([
    'French' => ['fr', '/fr/nouveautes'],
    'English' => ['en', '/en/whats-new'],
]);

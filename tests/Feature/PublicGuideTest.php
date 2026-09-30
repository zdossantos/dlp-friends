<?php

use App\Support\PublicUrls;

function guideStructuredData(string $html): array
{
    preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);

    return array_map(
        fn (string $json): array => json_decode($json, true, flags: JSON_THROW_ON_ERROR),
        $matches[1],
    );
}

test('localized search guides are useful indexable server-rendered pages', function (
    string $path,
    string $locale,
    string $title,
    string $heading,
    string $friendlyWording,
    string $adultWording,
    string $independenceWording,
) {
    config()->set('app.url', 'https://dlp-friends.example');

    $response = $this->get($path)
        ->assertOk()
        ->assertViewIs('guides.show')
        ->assertHeaderMissing('X-Robots-Tag')
        ->assertSee('<html lang="'.$locale.'">', false)
        ->assertSee('<title>'.$title.'</title>', false)
        ->assertSee('<h1', false)
        ->assertSee($heading)
        ->assertSee($friendlyWording)
        ->assertSee($adultWording)
        ->assertSee($independenceWording)
        ->assertSee('href="'.PublicUrls::matchingPath($locale).'"', false)
        ->assertSee('href="/'.$locale.'"', false)
        ->assertDontSee('type="module"', false);

    expect(substr_count($response->getContent(), '<h1'))->toBe(1);

    $schemas = guideStructuredData($response->getContent());
    expect(collect($schemas)->pluck('@type')->all())
        ->toContain('WebPage', 'BreadcrumbList');
})->with([
    'French friendships' => [
        '/fr/rencontres-amicales-disneyland-paris',
        'fr',
        'Rencontrer des amis fans de Disneyland Paris | DLP Friends',
        'Rencontrer des amis fans de Disneyland Paris',
        'rencontres strictement amicales',
        'personnes majeures',
        'indépendant de Disney et de Disneyland Paris',
    ],
    'English friendships' => [
        '/en/disneyland-paris-friendships',
        'en',
        'Meet Disneyland Paris friends | DLP Friends',
        'Meet friends who enjoy Disneyland Paris',
        'strictly friendly connections',
        'adults aged 18 and over',
        'independent from Disney and Disneyland Paris',
    ],
    'French solo visit' => [
        '/fr/aller-seul-disneyland-paris',
        'fr',
        'Aller seul à Disneyland Paris et rencontrer des amis | DLP Friends',
        'Aller seul à Disneyland Paris sans rester isolé',
        'rencontres strictement amicales',
        'personnes majeures',
        'indépendant de Disney et de Disneyland Paris',
    ],
    'English solo visit' => [
        '/en/visiting-disneyland-paris-solo',
        'en',
        'Visiting Disneyland Paris solo and meeting friends | DLP Friends',
        'Visiting Disneyland Paris solo without feeling alone',
        'strictly friendly connections',
        'adults aged 18 and over',
        'independent from Disney and Disneyland Paris',
    ],
]);

test('guides self-canonicalize and expose reciprocal language alternates', function (string $path, string $canonical, string $alternate) {
    config()->set('app.url', 'https://dlp-friends.example');

    $response = $this->get($path)->assertOk();

    $response
        ->assertSee('<link rel="canonical" href="https://dlp-friends.example'.$canonical.'">', false)
        ->assertSee('<link rel="alternate" hreflang="fr"', false)
        ->assertSee('<link rel="alternate" hreflang="en"', false)
        ->assertSee('<link rel="alternate" hreflang="x-default" href="https://dlp-friends.example'.$alternate.'">', false);
})->with([
    'French friendships' => ['/fr/rencontres-amicales-disneyland-paris', '/fr/rencontres-amicales-disneyland-paris', '/fr/rencontres-amicales-disneyland-paris'],
    'English friendships' => ['/en/disneyland-paris-friendships', '/en/disneyland-paris-friendships', '/fr/rencontres-amicales-disneyland-paris'],
    'French solo visit' => ['/fr/aller-seul-disneyland-paris', '/fr/aller-seul-disneyland-paris', '/fr/aller-seul-disneyland-paris'],
    'English solo visit' => ['/en/visiting-disneyland-paris-solo', '/en/visiting-disneyland-paris-solo', '/fr/aller-seul-disneyland-paris'],
]);

test('unknown guide locale and slug combinations are not indexable pages', function (string $path) {
    $this->get($path)->assertNotFound();
})->with([
    '/de/disneyland-paris-friendships',
    '/fr/disneyland-paris-friendships',
    '/en/rencontres-amicales-disneyland-paris',
    '/fr/guide-invente',
]);

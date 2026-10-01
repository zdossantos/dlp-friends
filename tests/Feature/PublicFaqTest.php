<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function faqStructuredData(string $html): array
{
    preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);

    return array_map(
        fn (string $json): array => json_decode($json, true, flags: JSON_THROW_ON_ERROR),
        $matches[1],
    );
}

test('localized faq pages expose useful visible answers in server rendered html', function (
    string $path,
    string $locale,
    string $title,
    array $questions,
    array $answers,
) {
    $response = $this->get($path)
        ->assertOk()
        ->assertViewIs('faq.show')
        ->assertHeaderMissing('X-Robots-Tag')
        ->assertSee('<html lang="'.$locale.'">', false)
        ->assertSee('<h1', false)
        ->assertSee($title)
        ->assertDontSee('type="module"', false);

    foreach ([...$questions, ...$answers] as $visibleCopy) {
        $response->assertSee($visibleCopy);
    }

    expect(substr_count($response->getContent(), '<h1'))->toBe(1);
})->with([
    'French' => [
        '/fr/faq',
        'fr',
        'Questions fréquentes sur DLP Friends',
        [
            'Comment fonctionne DLP Friends ?',
            'Faut-il avoir 18 ans pour utiliser DLP Friends ?',
            'Les rencontres sont-elles strictement amicales ?',
            'Comment DLP Friends protège-t-il ma vie privée ?',
            'Comment rencontrer un autre membre en sécurité ?',
            'Comment fonctionne la découverte réciproque ?',
            'DLP Friends est-il affilié à Disney ou à Disneyland Paris ?',
        ],
        [
            'profil amical',
            'réservé aux personnes majeures',
            'aucune mécanique romantique',
            'aucune ville ni région',
            'lieu public fréquenté',
            'envie réciproque',
            'service indépendant',
        ],
    ],
    'English' => [
        '/en/faq',
        'en',
        'Frequently asked questions about DLP Friends',
        [
            'How does DLP Friends work?',
            'Do I need to be 18 to use DLP Friends?',
            'Are connections strictly friendship-focused?',
            'How does DLP Friends protect my privacy?',
            'How can I meet another member safely?',
            'How does mutual discovery work?',
            'Is DLP Friends affiliated with Disney or Disneyland Paris?',
        ],
        [
            'friendly profile',
            'adults aged 18 and over',
            'no romantic mechanics',
            'city or region',
            'busy public place',
            'mutual interest',
            'independent service',
        ],
    ],
]);

test('faq pages self canonicalize and expose localized social metadata and reciprocal alternates', function (
    string $path,
    string $locale,
    string $title,
    string $description,
) {
    config()->set('app.url', 'https://dlp-friends.example');

    $response = $this->get($path)->assertOk();

    $response
        ->assertSee('<title>'.$title.'</title>', false)
        ->assertSee('<meta name="description" content="'.$description.'">', false)
        ->assertSee('<link rel="canonical" href="https://dlp-friends.example/'.$locale.'/faq">', false)
        ->assertSee('<link rel="alternate" hreflang="fr" href="https://dlp-friends.example/fr/faq">', false)
        ->assertSee('<link rel="alternate" hreflang="en" href="https://dlp-friends.example/en/faq">', false)
        ->assertSee('<link rel="alternate" hreflang="x-default" href="https://dlp-friends.example/fr/faq">', false)
        ->assertSee('<meta property="og:title" content="'.$title.'">', false)
        ->assertSee('<meta property="og:url" content="https://dlp-friends.example/'.$locale.'/faq">', false)
        ->assertSee('<meta name="twitter:card" content="summary">', false)
        ->assertSee('<meta name="twitter:title" content="'.$title.'">', false);
})->with([
    'French' => [
        '/fr/faq',
        'fr',
        'FAQ DLP Friends : fonctionnement, sécurité et confidentialité',
        'Retrouve les réponses essentielles sur DLP Friends, les rencontres strictement amicales entre adultes, la confidentialité et la sécurité.',
    ],
    'English' => [
        '/en/faq',
        'en',
        'DLP Friends FAQ: how it works, safety and privacy',
        'Find essential answers about DLP Friends, strictly friendship-focused connections between adults, privacy, and safety.',
    ],
]);

test('faq structured data describes only the visible page and breadcrumb', function (string $path, string $locale) {
    config()->set('app.url', 'https://dlp-friends.example');

    $response = $this->get($path)->assertOk();
    $schemas = faqStructuredData($response->getContent());

    expect(collect($schemas)->pluck('@type')->all())
        ->toBe(['WebPage', 'BreadcrumbList'])
        ->and($schemas[0]['url'])->toBe("https://dlp-friends.example/{$locale}/faq")
        ->and($schemas[0]['inLanguage'])->toBe($locale)
        ->and($response->getContent())->not->toContain('FAQPage');
})->with([
    'French' => ['/fr/faq', 'fr'],
    'English' => ['/en/faq', 'en'],
]);

test('authenticated members bypass the public faq', function (string $path) {
    $member = User::factory()->withProfile()->create();

    $this->actingAs($member)
        ->get($path)
        ->assertRedirect(route('app'));
})->with(['/fr/faq', '/en/faq']);

test('each landing links to its localized faq and the sitemap contains both variants', function (string $locale) {
    config()->set('app.url', 'https://dlp-friends.example');

    $this->get("/{$locale}")
        ->assertOk()
        ->assertSee('href="/'.$locale.'/faq"', false);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('https://dlp-friends.example/fr/faq', false)
        ->assertSee('https://dlp-friends.example/en/faq', false);
})->with(['fr', 'en']);

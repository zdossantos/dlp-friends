<?php

use App\Enums\ProductOnboardingStatus;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\ProductOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the public entry point redirects to the browser language landing page', function (string $language, string $path) {
    $this->withHeader('Accept-Language', $language)
        ->get('/')
        ->assertRedirect($path);
})->with([
    'French' => ['fr-FR,fr;q=0.9,en;q=0.8', '/fr'],
    'English' => ['en-GB,en;q=0.9,fr;q=0.8', '/en'],
    'unsupported language fallback' => ['de-DE,de;q=0.9', '/fr'],
]);

test('an explicit locale cookie takes priority over the browser language', function () {
    $this->withCookie('locale', 'en')
        ->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')
        ->get('/')
        ->assertRedirect('/en');
});

test('an Inertia visit to the public entry point requests a full localized document', function () {
    $assetVersion = app(HandleInertiaRequests::class)->version(request());

    $this->withHeader('Accept-Language', 'en-GB,en;q=0.9')
        ->withHeader('X-Inertia', 'true')
        ->withHeader('X-Inertia-Version', $assetVersion)
        ->get('/')
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', '/en');
});

test('an authenticated member bypasses the public landing page', function () {
    $member = User::factory()->withProfile(false)->create();
    ProductOnboarding::factory()->for($member)->create([
        'status' => ProductOnboardingStatus::Completed,
        'step' => null,
    ]);

    $this->actingAs($member)
        ->get('/')
        ->assertRedirect(route('app'));
});

test('the public landing is server rendered without application javascript', function () {
    $this->get('/fr')
        ->assertOk()
        ->assertViewIs('welcome')
        ->assertSee('<main', false)
        ->assertSee('Vis la magie à plusieurs')
        ->assertSee('href="/fr/matching"', false)
        ->assertSee('Comprendre nos suggestions')
        ->assertDontSee('type="module"', false);
});

test('each landing locale exposes localized indexable seo metadata', function (string $locale, string $title, string $description) {
    config()->set('app.url', 'https://dlp-friends.example');

    $response = $this->get("/{$locale}")
        ->assertOk()
        ->assertHeaderMissing('X-Robots-Tag')
        ->assertViewIs('welcome')
        ->assertViewHas('seo.locale', $locale)
        ->assertViewHas('seo.title', $title)
        ->assertViewHas('seo.description', $description)
        ->assertViewHas('seo.canonical', "https://dlp-friends.example/{$locale}")
        ->assertViewHas('seo.alternates.fr', 'https://dlp-friends.example/fr')
        ->assertViewHas('seo.alternates.en', 'https://dlp-friends.example/en')
        ->assertViewHas('seo.alternates.x_default', 'https://dlp-friends.example/fr');

    $response
        ->assertSee('<link rel="canonical" href="https://dlp-friends.example/'.$locale.'">', false)
        ->assertSee('<html lang="'.$locale.'">', false)
        ->assertSee('<meta property="og:title" content="'.$title.'">', false)
        ->assertSee('<script type="application/ld+json">', false)
        ->assertSee('SocialNetworkingApplication', false);
})->with([
    'French' => ['fr', 'DLP Friends — Rencontre d’autres fans de Disneyland Paris', 'Rencontre d’autres fans de Disneyland Paris, découvre vos passions communes et échange simplement.'],
    'English' => ['en', 'DLP Friends — Meet other Disneyland Paris fans', 'Meet other Disneyland Paris fans, discover the passions you share, and chat with ease.'],
]);

test('the sitemap contains localized public landing and legal pages', function () {
    config()->set('app.url', 'https://dlp-friends.example');

    $response = $this->get('/sitemap.xml')->assertOk();

    $response->assertSee('https://dlp-friends.example/fr', false)
        ->assertSee('https://dlp-friends.example/en', false)
        ->assertSee('https://dlp-friends.example/fr/matching', false)
        ->assertSee('https://dlp-friends.example/en/matching', false)
        ->assertSee('https://dlp-friends.example/fr/conditions-generales-utilisation', false)
        ->assertSee('https://dlp-friends.example/en/terms-of-use', false)
        ->assertSee('https://dlp-friends.example/fr/politique-confidentialite', false)
        ->assertSee('https://dlp-friends.example/en/privacy-policy', false)
        ->assertDontSee('/login', false)
        ->assertDontSee('/register', false)
        ->assertDontSee('/discover', false)
        ->assertDontSee('/conversations', false);
});

test('authentication and private pages instruct search engines not to index them', function () {
    $this->get('/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $this->get('/register')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $this->get('/discover')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

test('robots policy points to the sitemap and restricts crawling to public landings', function () {
    config()->set('app.url', 'https://dlp-friends.example');

    $this->get('/robots.txt')
        ->assertOk()
        ->assertSee("Allow: /fr\n", false)
        ->assertSee("Allow: /en\n", false)
        ->assertSee("Disallow: /\n", false)
        ->assertSee('Sitemap: https://dlp-friends.example/sitemap.xml', false);
});

test('google analytics and search console verification are disabled without configuration', function () {
    config()->set('services.google.analytics_id');
    config()->set('services.google.site_verification');

    $this->get('/fr')
        ->assertOk()
        ->assertDontSee('googletagmanager.com/gtag/js', false)
        ->assertDontSee('google-site-verification', false);
});

test('google analytics and search console verification are rendered when configured', function () {
    config()->set('services.google.analytics_id', 'G-TEST123456');
    config()->set('services.google.site_verification', 'search-console-token');

    $this->get('/fr')
        ->assertOk()
        ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-TEST123456', false)
        ->assertSee("gtag('config', 'G-TEST123456')", false)
        ->assertSee('<meta name="google-site-verification" content="search-console-token">', false);
});

test('google analytics is available on private application pages when configured', function () {
    config()->set('services.google.analytics_id', 'G-TEST123456');

    $this->get('/login')
        ->assertOk()
        ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-TEST123456', false)
        ->assertSee("gtag('config', 'G-TEST123456', { send_page_view: false })", false);
});

<?php

use App\Http\Middleware\EnsureDeploymentReady;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('contribution pages explain the public fork workflow and owner review in both languages', function (string $locale, string $path, string $heading, string $review) {
    config()->set('app.url', 'https://dlp-friends.example');

    $response = $this->get($path)
        ->assertOk()
        ->assertViewIs('contributing.show')
        ->assertHeaderMissing('X-Robots-Tag')
        ->assertSee('<html lang="'.$locale.'">', false)
        ->assertSee($heading)
        ->assertSee($review)
        ->assertSee('href="https://github.com/zdossantos/dlp-friends/fork"', false)
        ->assertSee('href="https://github.com/zdossantos/dlp-friends/blob/main/CONTRIBUTING.md"', false)
        ->assertSee('href="https://github.com/zdossantos/dlp-friends/compare"', false)
        ->assertSee('<link rel="canonical" href="https://dlp-friends.example'.$path.'">', false)
        ->assertSee('hreflang="fr" href="https://dlp-friends.example/fr/contribuer"', false)
        ->assertSee('hreflang="en" href="https://dlp-friends.example/en/contribute"', false)
        ->assertDontSee('type="module"', false);

    expect(substr_count($response->getContent(), '<h1'))->toBe(1);

    $this->get('/'.$locale)->assertOk()->assertSee('href="'.$path.'"', false);
})->with([
    'French' => ['fr', '/fr/contribuer', 'Contribuer à DLP Friends', 'Seul zdossantos valide et fusionne les contributions.'],
    'English' => ['en', '/en/contribute', 'Contribute to DLP Friends', 'Only zdossantos approves and merges contributions.'],
]);

test('members can also read the contribution page without being redirected', function () {
    $this->actingAs(User::factory()->withProfile()->create())
        ->get('/fr/contribuer')->assertOk();
});

test('the sitemap includes both contribution pages', function () {
    config()->set('app.url', 'https://dlp-friends.example');

    $this->get('/sitemap.xml')->assertOk()
        ->assertSee('https://dlp-friends.example/fr/contribuer', false)
        ->assertSee('https://dlp-friends.example/en/contribute', false);
});

test('contribution pages remain excluded from indexing on beta', function () {
    $this->withoutMiddleware(EnsureDeploymentReady::class);
    app()->detectEnvironment(fn (): string => 'beta');

    $this->actingAs(User::factory()->admin()->create())
        ->get('/fr/contribuer')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the bio link serves localized browser help without a redirect', function (string $language, string $title) {
    $this->withHeader('Accept-Language', $language)->get('/open')
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee($title)
        ->assertSee('readonly', false);
})->with([
    ['fr', 'Ouvre DLP Friends dans ton navigateur'],
    ['en', 'Open DLP Friends in your browser'],
]);

test('browser help keeps a fixed localized destination even with an untrusted target', function (string $locale) {
    config()->set('app.url', 'https://dlp-friends.example');

    $this->get("/{$locale}/open?url=https://evil.example&redirect=https://evil.example")
        ->assertOk()
        ->assertSee('href="/'.$locale.'"', false)
        ->assertSee('value="https://dlp-friends.example/'.$locale.'"', false)
        ->assertDontSee('evil.example');
})->with(['fr', 'en']);

test('Instagram visitors receive instructions even without javascript or a working external handoff', function () {
    $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone) Instagram 424.1')->get('/fr/open')
        ->assertOk()
        ->assertSee('menu du navigateur intégré')
        ->assertSee('Safari')
        ->assertSee('Chrome')
        ->assertSee('Copie le lien')
        ->assertSee('Continuer sur DLP Friends');
});

test('signed in members can still use browser help', function () {
    $this->actingAs(User::factory()->withProfile()->create())->get('/fr/open')
        ->assertOk()
        ->assertSee('Ouvre DLP Friends dans ton navigateur');
});

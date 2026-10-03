<?php

use App\Enums\SwipeDecision;
use App\Models\Swipe;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('a member revisits a passed profile and discovers a reciprocal friend', function (string $locale, int $width, int $height, string $theme) {
    Storage::fake('local');
    $actor = User::factory()->withProfile()->create(['locale' => $locale]);
    $target = User::factory()->withProfile()->create();
    $target->profile->update(['display_name' => 'Basile']);
    Swipe::factory()->create(['actor_user_id' => $target->id, 'target_user_id' => $actor->id, 'decision' => SwipeDecision::Like]);
    foreach ([$actor, $target] as $member) {
        Storage::disk('local')->put($member->profile->avatar->image_path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL8WQAAAABJRU5ErkJggg==',
        ));
    }
    $this->actingAs($actor);

    $page = visit('/discover')->resize($width, $height)->assertSee('Basile');
    $page->script("document.documentElement.classList.toggle('dark', '".$theme."' === 'dark');");
    $pass = $locale === 'fr' ? 'Passer ce profil' : 'Pass this profile';
    $title = $locale === 'fr' ? 'Profils passés' : 'Passed profiles';
    $emptyDiscovery = $locale === 'fr' ? 'Tu as exploré tous les profils disponibles' : 'You have explored every available profile';
    $match = $locale === 'fr' ? 'Vos univers se croisent' : 'Your worlds cross paths';
    $continue = $locale === 'fr' ? 'Continuer à explorer' : 'Keep exploring';
    $emptyHistory = $locale === 'fr' ? 'Aucun profil passé disponible' : 'No passed profiles available';
    $page->keys('[aria-label="'.$pass.'"]', 'Enter')->assertSee($emptyDiscovery);
    $page->click($title)->assertSee('Basile')->assertNoAccessibilityIssues()
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertPresent('[data-test="member-bottom-navigation"]')
        ->keys('[data-test="passed-profile"]', 'Enter')->assertSee('Basile')
        ->keys('[data-test="like-member"]', 'Enter')->assertSee($match);
    $page->click($continue)->assertSee($emptyHistory)
        ->assertNoJavaScriptErrors();
    $this->assertDatabaseCount('matches', 1);
    $this->assertDatabaseCount('conversations', 1);
    $this->assertDatabaseHas('swipes', ['actor_user_id' => $actor->id, 'target_user_id' => $target->id, 'decision' => 'like']);
})->with([['fr', 320, 700, 'dark'], ['en', 1440, 900, 'light']]);

test('passed profiles paginate and retain the current page after a network failure', function () {
    Storage::fake('local');
    $actor = User::factory()->withProfile()->create();
    $targets = User::factory()->withProfile()->count(21)->create();
    foreach ($targets as $target) {
        Swipe::factory()->create(['actor_user_id' => $actor->id, 'target_user_id' => $target->id, 'decision' => SwipeDecision::Pass]);
    }
    foreach ([$actor, ...$targets] as $member) {
        Storage::disk('local')->put($member->profile->avatar->image_path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL8WQAAAABJRU5ErkJggg==',
        ));
    }
    $this->actingAs($actor);
    $page = visit('/discover/passed')->resize(320, 700)->assertCount('[data-test="passed-profile"]', 20);
    $page->page()->evaluate(<<<'JS'
        (() => {
        window.__passedOpen ??= XMLHttpRequest.prototype.open;
        window.__passedSend ??= XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.open = function (method, url, ...rest) {
            this.__passedUrl = String(url);
            return window.__passedOpen.call(this, method, url, ...rest);
        };
        XMLHttpRequest.prototype.send = function (body) {
            if ((this.__passedUrl ?? '').includes('/discover/passed?page=2')) {
                window.__passedRequest = this;
                return;
            }
            return window.__passedSend.call(this, body);
        };
        return true;
        })();
    JS);
    $page->click('Suivant')->assertSee('Chargement des profils…')->assertPresent('[aria-busy="true"]');
    $page->page()->evaluate("XMLHttpRequest.prototype.open = window.__passedOpen; XMLHttpRequest.prototype.send = window.__passedSend; window.__passedRequest.dispatchEvent(new ProgressEvent('error'));");
    $page->assertSee('Impossible de charger les profils. Vérifie ta connexion puis réessaie.')
        ->assertCount('[data-test="passed-profile"]', 20)
        ->click('Réessayer')->assertSee('Page 2 sur 2')->assertCount('[data-test="passed-profile"]', 1);
    $page->click('Précédent')->assertSee('Page 1 sur 2')->assertCount('[data-test="passed-profile"]', 20)
        ->assertNoJavaScriptErrors();
});

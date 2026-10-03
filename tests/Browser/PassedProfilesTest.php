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
    $page->assertPresent('a[data-slot="button"][aria-label="'.$title.'"] svg')
        ->assertScript("(() => { const button = document.querySelector('a[aria-label=\"{$title}\"]'); return button.textContent.trim() === '' && button.getBoundingClientRect().width >= 44 && button.getBoundingClientRect().height >= 44; })()", true);
    $page->keys('[aria-label="'.$pass.'"]', 'Enter')->assertSee($emptyDiscovery);
    $page->keys('a[aria-label="'.$title.'"]', 'Enter')->assertSee('Basile')->assertNoAccessibilityIssues()
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

test('closing and discovering a passed profile preserve the history page and scroll', function () {
    Storage::fake('local');
    $actor = User::factory()->withProfile()->create();
    for ($index = 0; $index < 39; $index++) {
        $target = User::factory()->withProfile()->create();
        Swipe::factory()->create(['actor_user_id' => $actor->id, 'target_user_id' => $target->id, 'decision' => SwipeDecision::Pass]);
        Storage::disk('local')->put($target->profile->avatar->image_path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL8WQAAAABJRU5ErkJggg=='));
    }
    $this->actingAs($actor);
    $page = visit('/discover/passed?page=2')->resize(390, 844)->assertSee('Page 2 sur 2');
    $page->script(<<<'JS'
        (() => {
            const list = document.querySelector('[data-test="passed-profile-list"]');
            const link = list.querySelectorAll('[data-test="passed-profile"]')[6];
            link.focus();
            list.scrollTop = 500;
            window.__passedScroll = list.scrollTop;
            window.__passedId = link.getAttribute('data-profile-id');
            return true;
        })()
    JS);
    $page->keys('[data-test="passed-profile-list"] li:nth-child(7) button', 'Enter')
        ->assertPresent('[data-test="passed-profile-drawer"] [data-test="like-member"]')
        ->assertScript("window.location.pathname === '/discover/passed' && window.location.search === '?page=2'", true)
        ->assertNoAccessibilityIssues();
    $page->keys('[data-test="passed-profile-drawer"]', 'Escape')
        ->assertMissing('[data-test="passed-profile-drawer"]')
        ->assertScript("document.querySelector('[data-test=passed-profile-list]').scrollTop === window.__passedScroll", true);
    $page->keys('[data-test="passed-profile-list"] li:nth-child(7) button', 'Enter')
        ->assertPresent('[data-test="passed-profile-drawer"] [data-test="like-member"]')
        ->keys('[data-test="like-member"]', 'Enter')
        ->assertMissing('[data-test="passed-profile-drawer"]')
        ->assertSee('Page 2 sur 2')
        ->assertScript("Math.abs(document.querySelector('[data-test=passed-profile-list]').scrollTop - window.__passedScroll) <= 1", true)
        ->assertScript("!Array.from(document.querySelectorAll('[data-test=passed-profile]')).some(link => link.getAttribute('data-profile-id') === window.__passedId)", true)
        ->assertNoJavaScriptErrors();
});

test('a profile becoming unavailable in the drawer shows the conversion error', function () {
    Storage::fake('local');
    $actor = User::factory()->withProfile()->create();
    $target = User::factory()->withProfile()->create();
    Swipe::factory()->create(['actor_user_id' => $actor->id, 'target_user_id' => $target->id, 'decision' => SwipeDecision::Pass]);
    Storage::disk('local')->put($target->profile->avatar->image_path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL8WQAAAABJRU5ErkJggg=='));
    $this->actingAs($actor);
    $page = visit('/discover/passed')->resize(390, 844)
        ->keys('[data-test="passed-profile"]', 'Enter')
        ->assertPresent('[data-test="passed-profile-drawer"] [data-test="like-member"]');
    $target->profile->update(['visibility' => 'hidden']);
    $page->keys('[data-test="like-member"]', 'Enter')
        ->assertSee(__('discovery.errors.target_unavailable'))
        ->assertPresent('[data-test="passed-profile-drawer"]')
        ->assertMissing('[data-test="like-member"]')
        ->keys('[data-test="passed-profile-drawer"]', 'Escape')
        ->assertSee('Aucun profil passé disponible')
        ->assertNoJavaScriptErrors();
    expect(Swipe::query()->first()->decision)->toBe(SwipeDecision::Pass);
});

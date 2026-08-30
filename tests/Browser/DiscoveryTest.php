<?php

use App\Actions\CreateSwipe;
use App\Enums\SwipeDecision;
use App\Models\Interest;
use App\Models\MemberMatch;
use App\Models\Swipe;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

function discoveryMember(string $displayName): User
{
    $user = User::factory()->withProfile()->create();
    $user->profile?->update(['display_name' => $displayName]);
    Storage::disk('local')->put($user->profile->avatar->image_path, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL8WQAAAABJRU5ErkJggg==',
    ));

    return $user;
}

beforeEach(fn () => Storage::fake('local'));

test('discovery renders its deferred empty state and limits the stack to five profiles', function () {
    $actor = discoveryMember('Alice');
    $this->actingAs($actor);

    visit('/discover')
        ->assertSee('Découvrir')
        ->assertSee('Vous avez exploré tous les profils disponibles')
        ->assertNoJavaScriptErrors();

    $candidates = User::factory()->withProfile()->count(6)->create();
    $denseInterests = Interest::factory()->count(5)->create();
    $candidates->first()?->profile?->interests()->attach($denseInterests->modelKeys());

    visit('/discover')
        ->assertPresent('[aria-label="Profils à découvrir"]')
        ->assertCount('[data-test="discovery-card-stack-item"]', 5)
        ->assertScript(
            "[...document.querySelectorAll('[data-test=\"discovery-card-stack-item\"]')].slice(1).every((card) => card.ariaHidden === 'true' && card.inert)",
            true,
        )
        ->assertScript(
            "new Set([...document.querySelectorAll('[data-test=discovery-card]')].map((card) => card.offsetHeight)).size === 1",
            true,
        )
        ->assertScript(
            "document.querySelectorAll('[data-test=discovery-card]')[1].getBoundingClientRect().bottom > document.querySelectorAll('[data-test=discovery-card]')[0].getBoundingClientRect().bottom",
            true,
        )
        ->assertNoJavaScriptErrors();
});

test('discovery cards keep predictable content zones for extreme profile content', function () {
    $actor = discoveryMember('Alice');
    $longProfile = discoveryMember('Un nom de membre volontairement très long pour la carte');
    $shortProfile = discoveryMember('Zoé');
    $longProfile->profile?->update([
        'bio' => str_repeat('Une longue bio destinée à vérifier la troncature visuelle. ', 8),
        'visit_frequency' => null,
    ]);
    $shortProfile->profile?->update(['bio' => null]);
    $denseInterests = Interest::factory()->count(5)->create();
    $longProfile->profile?->interests()->attach($denseInterests->modelKeys());
    $actor->profile?->interests()->attach($denseInterests->firstOrFail());
    $this->actingAs($actor);

    visit('/discover')
        ->on()->mobile()
        ->assertCount('[data-test="discovery-card-stack-item"]', 2)
        ->assertScript("new Set([...document.querySelectorAll('[data-test=discovery-identity]')].map((element) => getComputedStyle(element).height)).size === 1", true)
        ->assertScript("new Set([...document.querySelectorAll('[data-test=discovery-bio]')].map((element) => getComputedStyle(element).height)).size === 1", true)
        ->assertScript("new Set([...document.querySelectorAll('[data-test=discovery-frequency]')].map((element) => getComputedStyle(element).height)).size === 1", true)
        ->assertScript(
            "document.querySelector('[data-test=discovery-bio]').scrollHeight > document.querySelector('[data-test=discovery-bio]').clientHeight",
            true,
        )
        ->assertScript(
            "document.querySelector('[data-test=discovery-information-sheet]').scrollHeight <= document.querySelector('[data-test=discovery-information-sheet]').clientHeight",
            true,
        )
        ->assertScript(
            "document.querySelector('[data-test=discovery-affinities]').getBoundingClientRect().top < document.querySelector('[data-test=discovery-bio]').getBoundingClientRect().top",
            true,
        )
        ->assertScript(
            "[...document.querySelectorAll('[data-test=discovery-card-stack-item]:first-child [data-test=discovery-interest]')].every((interest) => interest.getBoundingClientRect().bottom <= document.querySelector('[data-test=discovery-affinities]').getBoundingClientRect().bottom)",
            true,
        )
        ->assertDontSee('Même fréquence de visite')
        ->assertNoJavaScriptErrors();
});

test('discovery decisions are icon controls below the card with accessible touch targets', function () {
    $actor = discoveryMember('Alice');
    discoveryMember('Basile');
    $this->actingAs($actor);

    visit('/discover')
        ->on()->mobile()
        ->assertPresent('[data-test="discovery-actions"][aria-label="Actions du profil"]')
        ->assertPresent('[aria-label="Passer ce profil"] .sr-only')
        ->assertPresent('[aria-label="Aimer ce profil"] .sr-only')
        ->assertScript(
            "document.querySelector('[data-test=discovery-actions]').getBoundingClientRect().top >= document.querySelector('[data-test=discovery-card]').getBoundingClientRect().bottom",
            true,
        )
        ->assertScript(
            "[...document.querySelectorAll('[data-test=discovery-actions] button')].every((button) => button.getBoundingClientRect().width >= 44 && button.getBoundingClientRect().height >= 44)",
            true,
        )
        ->assertNoJavaScriptErrors();
});

test('discovery renders its loading state while suggestions are deferred', function () {
    $actor = discoveryMember('Alice');
    discoveryMember('Basile');
    $this->actingAs($actor);

    $page = visit('/profile');
    $page->script(<<<'JS'
        if (!window.__deferredXhrInstalled) {
            window.__deferredXhrInstalled = true;
            window.__realDeferredXhrOpen = XMLHttpRequest.prototype.open;
            window.__realDeferredXhrSetRequestHeader = XMLHttpRequest.prototype.setRequestHeader;
            window.__realDeferredXhrSend = XMLHttpRequest.prototype.send;
            XMLHttpRequest.prototype.open = function (method, url, ...rest) {
                this.__deferredRequestUrl = String(url);
                this.__deferredRequestHeaders = {};

                return window.__realDeferredXhrOpen.call(this, method, url, ...rest);
            };
            XMLHttpRequest.prototype.setRequestHeader = function (name, value) {
                this.__deferredRequestHeaders[String(name).toLowerCase()] = String(value);

                return window.__realDeferredXhrSetRequestHeader.call(this, name, value);
            };
            XMLHttpRequest.prototype.send = function (body) {
                const requestUrl = this.__deferredRequestUrl ?? '';
                const requestHeaders = this.__deferredRequestHeaders ?? {};
                const partialData = requestHeaders['x-inertia-partial-data'] ?? '';

                if (requestUrl.includes('/discover') && partialData.includes('suggestions')) {
                    const request = this;
                    window.__releaseDeferredRequest = () => {
                        window.__releaseDeferredRequest = undefined;
                        window.__realDeferredXhrSend.call(request, body);
                    };

                    return;
                }

                return window.__realDeferredXhrSend.call(this, body);
            };
        }
        JS);
    $page->click('[aria-label="Découvrir"]')
        ->assertSee('Recherche de profils…')
        ->assertPresent('[aria-busy="true"]');
    $page->script('window.__releaseDeferredRequest()');
    $page->assertSee('Basile')
        ->assertNoJavaScriptErrors();
});

test('the top discovery card accepts keyboard and accessible decisions', function () {
    $actor = discoveryMember('Alice');
    $passed = discoveryMember('Basile');
    $commonInterest = Interest::factory()->create(['name' => 'Spectacles']);
    $targetInterest = Interest::factory()->create(['name' => 'Pins']);
    $otherTargetInterests = Interest::factory()->count(3)->sequence(
        ['name' => 'Food'],
        ['name' => 'Chill'],
        ['name' => 'Événements'],
    )->create();
    $actor->profile?->interests()->attach($commonInterest);
    $passed->profile?->interests()->attach([
        $commonInterest->id,
        $targetInterest->id,
        ...$otherTargetInterests->modelKeys(),
    ]);
    $this->actingAs($actor);

    $page = visit('/discover')
        ->on()->mobile()
        ->assertPresent('[aria-label="Passer ce profil"]')
        ->assertPresent('[aria-label="Aimer ce profil"]')
        ->assertPresent('[data-test="discovery-avatar-hero"]')
        ->assertPresent('[data-test="discovery-information-sheet"]')
        ->assertSee('Spectacles')
        ->assertSee('Pins')
        ->assertPresent('[data-test="discovery-interest"][data-common="true"]')
        ->assertPresent('[data-test="discovery-interest"][data-common="false"]')
        ->assertCount('[data-test="discovery-interest"]', 5)
        ->assertScript(
            "document.querySelector('[data-test=discovery-avatar-hero]').getBoundingClientRect().height >= 160 && document.querySelector('[data-test=discovery-avatar-hero]').getBoundingClientRect().height <= 200",
            true,
        )
        ->assertScript(
            "document.querySelector('[data-test=discovery-avatar-hero] img').getBoundingClientRect().top >= document.querySelector('[data-test=discovery-avatar-hero]').getBoundingClientRect().top + 8 && document.querySelector('[data-test=discovery-avatar-hero] img').getBoundingClientRect().bottom <= document.querySelector('[data-test=discovery-avatar-hero]').getBoundingClientRect().bottom",
            true,
        )
        ->assertScript(
            "document.querySelector('[data-test=discovery-actions]').getBoundingClientRect().top >= document.querySelector('[data-test=discovery-card]').getBoundingClientRect().bottom",
            true,
        )
        ->assertScript(
            "document.querySelector('[aria-label=\"Passer ce profil\"]').classList.contains('sr-only')",
            false,
        )
        ->assertScript(
            "document.querySelector('[data-test=discovery-avatar-hero]').getBoundingClientRect().height >= 160 && document.querySelector('[data-test=discovery-avatar-hero]').getBoundingClientRect().height <= 200",
            true,
        )
        ->assertScript(
            'document.querySelector(\'[data-test=member-shell-content]\').scrollHeight <= document.querySelector(\'[data-test=member-shell-content]\').clientHeight',
            true,
        )
        ->assertScript(
            "document.querySelector('[data-test=discovery-card-stack-item]').getBoundingClientRect().bottom <= document.querySelector('[data-test=member-shell-content]').getBoundingClientRect().bottom",
            true,
        )
        ->assertScript(
            "parseFloat(getComputedStyle(document.querySelector('[data-test=discovery-page]')).paddingBottom) >= 16",
            true,
        )
        ->keys('[data-test="discovery-card-stack-item"] [tabindex="0"]', 'ArrowLeft')
        ->assertSee('Vous avez exploré tous les profils disponibles');

    $this->assertDatabaseHas('swipes', [
        'actor_user_id' => $actor->id,
        'target_user_id' => $passed->id,
        'decision' => SwipeDecision::Pass->value,
    ]);

    $liked = discoveryMember('Chloé');
    $page->navigate('/discover')
        ->assertSee('Chloé');
    $page->script("document.querySelector('[aria-label=\"Aimer ce profil\"]').click()");
    $page
        ->assertSee('Vous avez exploré tous les profils disponibles');

    $this->assertDatabaseHas('swipes', [
        'actor_user_id' => $actor->id,
        'target_user_id' => $liked->id,
        'decision' => SwipeDecision::Like->value,
    ]);
    $this->assertDatabaseCount('swipes', 2);
});

test('an accepted swipe refreshes only the updated discovery data', function () {
    $actor = discoveryMember('Alice');
    discoveryMember('Basile');
    $this->actingAs($actor);

    $page = visit('/discover')->assertSee('Basile');
    $page->script(<<<'JS'
        window.__swipePartialData = '';
        window.__realPartialXhrOpen = XMLHttpRequest.prototype.open;
        window.__realPartialXhrSetRequestHeader = XMLHttpRequest.prototype.setRequestHeader;
        XMLHttpRequest.prototype.open = function (method, url, ...rest) {
            this.__isSwipeRequest = String(url).includes('/swipe');

            return window.__realPartialXhrOpen.call(this, method, url, ...rest);
        };
        XMLHttpRequest.prototype.setRequestHeader = function (name, value) {
            if (
                this.__isSwipeRequest &&
                String(name).toLowerCase() === 'x-inertia-partial-data'
            ) {
                window.__swipePartialData = String(value);
            }

            return window.__realPartialXhrSetRequestHeader.call(this, name, value);
        };
        true;
    JS);

    $page->script(<<<'JS'
        document.querySelector('[aria-label="Passer ce profil"]').click()
    JS);
    $page
        ->assertSee('Vous avez exploré tous les profils disponibles')
        ->assertScript(
            "window.__swipePartialData.split(',').sort().join(',')",
            'match,suggestions',
        );
});

test('pointer gestures follow the card and enforce the horizontal threshold', function () {
    $actor = discoveryMember('Alice');
    $target = discoveryMember('Basile');
    $this->actingAs($actor);

    $page = visit('/discover');
    $card = "document.querySelector('[data-test=\"discovery-card-stack-item\"] [tabindex=\"0\"]')";
    $actions = "document.querySelector('[data-test=\"discovery-card-stack-item\"] [data-test=\"discovery-actions\"]')";

    $page->script("{$card}.setPointerCapture = () => {}; {$card}.hasPointerCapture = () => false; {$card}.releasePointerCapture = () => {};");
    $page->script("{$card}.dispatchEvent(new PointerEvent('pointerdown', { pointerId: 1, clientX: 100, clientY: 100, bubbles: true })); {$card}.dispatchEvent(new PointerEvent('pointermove', { pointerId: 1, clientX: 171, clientY: 100, bubbles: true }));");
    $page->assertScript("{$card}.style.transform.includes('71px')", true)
        ->assertScript("getComputedStyle({$actions}).transform", 'none');
    $page->script("{$card}.dispatchEvent(new PointerEvent('pointerup', { pointerId: 1, clientX: 171, clientY: 100, bubbles: true }));");
    $page->assertScript("{$card}.style.transform.includes('0px')", true);
    $this->assertDatabaseCount('swipes', 0);

    $page->script("{$card}.dispatchEvent(new PointerEvent('pointerdown', { pointerId: 2, clientX: 100, clientY: 100, bubbles: true })); {$card}.dispatchEvent(new PointerEvent('pointermove', { pointerId: 2, clientX: 172, clientY: 100, bubbles: true })); {$card}.dispatchEvent(new PointerEvent('pointerup', { pointerId: 2, clientX: 172, clientY: 100, bubbles: true }));");
    $page->assertSee('Vous avez exploré tous les profils disponibles');

    $this->assertDatabaseHas('swipes', [
        'actor_user_id' => $actor->id,
        'target_user_id' => $target->id,
        'decision' => SwipeDecision::Like->value,
    ]);
});

test('a tap opens the profile while a horizontal drag keeps the swipe interaction', function () {
    $actor = discoveryMember('Alice');
    $target = discoveryMember('Basile');
    $this->actingAs($actor);

    $page = visit('/discover')->on()->mobile();
    $card = "document.querySelector('[data-test=discovery-card]')";

    $page->click('[data-test="discovery-avatar-hero"]')
        ->assertPathIs("/members/{$target->id}");

    $page->navigate('/discover');
    $page->script("{$card}.setPointerCapture = () => {}; {$card}.hasPointerCapture = () => false; {$card}.releasePointerCapture = () => {};");
    $page->script("{$card}.dispatchEvent(new PointerEvent('pointerdown', { pointerId: 21, isPrimary: true, clientX: 100, clientY: 100, bubbles: true })); {$card}.dispatchEvent(new PointerEvent('pointermove', { pointerId: 21, clientX: 140, clientY: 100, bubbles: true })); {$card}.dispatchEvent(new PointerEvent('pointerup', { pointerId: 21, clientX: 140, clientY: 100, bubbles: true })); {$card}.click();");
    $page->assertPathIs('/discover');
    $this->assertDatabaseCount('swipes', 0);
});

test('vertical and cancelled pointer gestures return the card to its centre', function () {
    $actor = discoveryMember('Alice');
    discoveryMember('Basile');
    $this->actingAs($actor);

    $page = visit('/discover');
    $card = "document.querySelector('[data-test=\"discovery-card-stack-item\"] [tabindex=\"0\"]')";
    $page->script("{$card}.setPointerCapture = () => {}; {$card}.hasPointerCapture = () => false; {$card}.releasePointerCapture = () => {};");
    $page->script("{$card}.dispatchEvent(new PointerEvent('pointerdown', { pointerId: 3, clientX: 100, clientY: 100, bubbles: true })); {$card}.dispatchEvent(new PointerEvent('pointermove', { pointerId: 3, clientX: 180, clientY: 220, bubbles: true })); {$card}.dispatchEvent(new PointerEvent('pointerup', { pointerId: 3, clientX: 180, clientY: 220, bubbles: true }));");
    $page->assertScript("{$card}.style.transform.includes('0px')", true);
    $page->script("{$card}.dispatchEvent(new PointerEvent('pointerdown', { pointerId: 4, clientX: 100, clientY: 100, bubbles: true })); {$card}.dispatchEvent(new PointerEvent('pointermove', { pointerId: 4, clientX: 190, clientY: 100, bubbles: true })); {$card}.dispatchEvent(new PointerEvent('pointercancel', { pointerId: 4, clientX: 190, clientY: 100, bubbles: true }));");
    $page->assertScript("{$card}.style.transform.includes('0px')", true);
    $this->assertDatabaseCount('swipes', 0);
});

test('a discovery card captures one pointer and ignores other pointer identifiers', function () {
    $actor = discoveryMember('Alice');
    $target = discoveryMember('Basile');
    $this->actingAs($actor);

    $page = visit('/discover')->assertSee('Basile');
    $card = "document.querySelector('[data-test=\"discovery-card-stack-item\"] [tabindex=\"0\"]')";
    $page->script("window.__capturedPointer = null; window.__releasedPointer = null; {$card}.setPointerCapture = (id) => window.__capturedPointer = id; {$card}.hasPointerCapture = (id) => window.__capturedPointer === id; {$card}.releasePointerCapture = (id) => window.__releasedPointer = id; true;");
    $page->script("{$card}.dispatchEvent(new PointerEvent('pointerdown', { pointerId: 7, isPrimary: true, clientX: 200, clientY: 100, bubbles: true })); {$card}.dispatchEvent(new PointerEvent('pointerup', { pointerId: 8, clientX: 100, clientY: 100, bubbles: true }));");
    $page->assertScript('window.__capturedPointer', 7)
        ->assertScript('window.__releasedPointer', null);
    $this->assertDatabaseCount('swipes', 0);

    $page->script("{$card}.dispatchEvent(new PointerEvent('pointerup', { pointerId: 7, clientX: 100, clientY: 100, bubbles: true }));");
    $page->assertSee('Vous avez exploré tous les profils disponibles')
        ->assertScript('window.__releasedPointer', 7);
    $this->assertDatabaseHas('swipes', [
        'actor_user_id' => $actor->id,
        'target_user_id' => $target->id,
        'decision' => SwipeDecision::Pass->value,
    ]);
});

test('a reciprocal like opens a dismissible match dialog only once', function () {
    $actor = discoveryMember('Alice');
    $target = discoveryMember('Basile');
    Swipe::factory()->create([
        'actor_user_id' => $target->id,
        'target_user_id' => $actor->id,
        'decision' => SwipeDecision::Like,
    ]);
    $this->actingAs($actor);

    $page = visit('/discover')
        ->assertSee('Basile');
    $page->script("document.querySelector('[aria-label=\"Aimer ce profil\"]').click()");
    $page->assertSee('C’est un match !')
        ->assertSee('Basile a aussi aimé votre profil.')
        ->assertPresent('[data-slot="dialog-title"]')
        ->assertPresent('[data-slot="dialog-description"]')
        ->assertSeeLink('Ouvrir la conversation');

    $conversationId = MemberMatch::query()->firstOrFail()->conversation()->firstOrFail()->id;
    $page->assertAttribute(
        '[data-test="open-match-conversation"]',
        'href',
        "/conversations/{$conversationId}",
    )
        ->click('Continuer à découvrir')
        ->assertNotPresent('[data-slot="dialog-title"]');

    $page->navigate('/discover')
        ->assertNotPresent('[data-slot="dialog-title"]');
});

test('a member sends the first message immediately after opening a new match conversation', function () {
    $actor = discoveryMember('Alice');
    $target = discoveryMember('Basile');
    Swipe::factory()->create([
        'actor_user_id' => $target->id,
        'target_user_id' => $actor->id,
        'decision' => SwipeDecision::Like,
    ]);
    $this->actingAs($actor);

    $page = visit('/discover')
        ->assertSee('Basile')
        ->click('[aria-label="Aimer ce profil"]')
        ->assertSee('C’est un match !')
        ->click('[data-test="open-match-conversation"]')
        ->assertPathBeginsWith('/conversations/');

    $page->script("document.querySelector('meta[name=csrf-token]').content = 'stale-token'; true;");
    $page->script(<<<'JS'
        window.__realFirstMessageFetch = window.fetch;
        window.fetch = async (...args) => {
            if (args[1]?.method === 'POST') {
                await new Promise((resolve) => setTimeout(resolve, 100));
            }

            return window.__realFirstMessageFetch(...args);
        };
        true;
    JS);

    $page->fill('content', 'Bonjour Basile !');
    $page->script("document.querySelector('[aria-label=\"Envoyer le message\"]').click(); document.querySelector('[aria-label=\"Envoyer le message\"]').click(); true;");
    $page
        ->assertSee('Bonjour Basile !')
        ->assertValue('content', '')
        ->assertScript("document.querySelectorAll('[data-message-id]').length", 1)
        ->assertNoJavaScriptErrors();

    $conversation = MemberMatch::query()->firstOrFail()->conversation()->firstOrFail();

    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conversation->id,
        'author_user_id' => $actor->id,
        'content' => 'Bonjour Basile !',
    ]);
    $this->assertDatabaseCount('messages', 1);
});

test('a network failure keeps the original decision available for retry', function () {
    $actor = discoveryMember('Alice');
    $target = discoveryMember('Basile');
    $this->actingAs($actor);

    $page = visit('/discover')->assertSee('Basile');
    $page->script(<<<'JS'
        window.__failedDiscoveryRequest = false;
        window.__realXhrOpen = XMLHttpRequest.prototype.open;
        window.__realXhrSend = XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.open = function (method, url, ...rest) {
            this.__browserTestUrl = String(url);

            return window.__realXhrOpen.call(this, method, url, ...rest);
        };
        XMLHttpRequest.prototype.send = function (body) {
            if (!window.__failedDiscoveryRequest && this.__browserTestUrl.includes('/swipe')) {
                window.__failedDiscoveryRequest = true;
                queueMicrotask(() => this.dispatchEvent(new ProgressEvent('error')));

                return;
            }

            return window.__realXhrSend.call(this, body);
        };
        true;
    JS);

    $page->script("document.querySelector('[aria-label=\"Aimer ce profil\"]').click(); document.querySelector('[aria-label=\"Aimer ce profil\"]').click();");
    $page->assertPresent('[role="alert"]')
        ->assertSee('La connexion a échoué avant l’enregistrement de cette décision.')
        ->assertPresent('button[aria-label="Réessayer"]')
        ->assertSee('Basile');

    $this->assertDatabaseCount('swipes', 0);

    $page->click('Réessayer')
        ->assertSee('Vous avez exploré tous les profils disponibles');

    $this->assertDatabaseHas('swipes', [
        'actor_user_id' => $actor->id,
        'target_user_id' => $target->id,
        'decision' => SwipeDecision::Like->value,
    ]);
});

test('a validation response keeps the card and retries the same decision', function () {
    $this->app->instance(CreateSwipe::class, new class extends CreateSwipe
    {
        private bool $failedOnce = false;

        public function handle(User $actor, User $target, SwipeDecision $decision): ?MemberMatch
        {
            if (! $this->failedOnce) {
                $this->failedOnce = true;

                throw ValidationException::withMessages([
                    'target' => 'Ce profil n’est pas disponible.',
                ]);
            }

            return parent::handle($actor, $target, $decision);
        }
    });
    $actor = discoveryMember('Alice');
    $target = discoveryMember('Basile');
    $this->actingAs($actor);

    $page = visit('/discover')->assertSee('Basile');
    $page->script("document.querySelector('[aria-label=\"Aimer ce profil\"]').click(); document.querySelector('[aria-label=\"Aimer ce profil\"]').click();");
    $page->assertPresent('[role="alert"]')
        ->assertSee('Ce profil n’est pas disponible.')
        ->assertSee('Basile');
    $this->assertDatabaseCount('swipes', 0);

    $page->click('Réessayer')
        ->assertSee('Vous avez exploré tous les profils disponibles');
    $this->assertDatabaseHas('swipes', [
        'actor_user_id' => $actor->id,
        'target_user_id' => $target->id,
        'decision' => SwipeDecision::Like->value,
    ]);
});

test('a stale failed decision is cleared when a replacement suggestion arrives', function () {
    $actor = discoveryMember('Alice');
    $target = discoveryMember('Basile');
    $this->actingAs($actor);

    $page = visit('/discover')->assertSee('Basile');
    $page->script(<<<'JS'
        window.__failedDiscoveryRequest = false;
        window.__realXhrOpen = XMLHttpRequest.prototype.open;
        window.__realXhrSend = XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.open = function (method, url, ...rest) {
            this.__browserTestUrl = String(url);

            return window.__realXhrOpen.call(this, method, url, ...rest);
        };
        XMLHttpRequest.prototype.send = function (body) {
            if (!window.__failedDiscoveryRequest && this.__browserTestUrl.includes('/swipe')) {
                window.__failedDiscoveryRequest = true;
                queueMicrotask(() => this.dispatchEvent(new ProgressEvent('error')));

                return;
            }

            return window.__realXhrSend.call(this, body);
        };
        true;
    JS);
    $page->script("document.querySelector('[aria-label=\"Aimer ce profil\"]').click()");
    $page->assertPresent('[role="alert"]');

    Swipe::factory()->create([
        'actor_user_id' => $actor->id,
        'target_user_id' => $target->id,
        'decision' => SwipeDecision::Pass,
    ]);
    $replacement = discoveryMember('Chloé');
    $page->script("document.querySelector('#app').__vue_app__.config.globalProperties.\$inertia.reload(); true;");
    $page->assertSee('Chloé')
        ->assertNotPresent('[role="alert"]')
        ->assertNotPresent('button[aria-label="Réessayer"]');
    $this->assertDatabaseMissing('swipes', [
        'actor_user_id' => $actor->id,
        'target_user_id' => $replacement->id,
    ]);
});

test('an unexpected HTTP response retains the card and exposes retry', function () {
    $this->app->instance(CreateSwipe::class, new class extends CreateSwipe
    {
        public function handle(User $actor, User $target, SwipeDecision $decision): ?MemberMatch
        {
            throw new RuntimeException('browser test failure');
        }
    });
    $actor = discoveryMember('Alice');
    discoveryMember('Basile');
    $this->actingAs($actor);

    $page = visit('/discover')->assertSee('Basile');
    $page->script("document.querySelector('[aria-label=\"Passer ce profil\"]').click()");
    $page->assertPresent('[role="alert"]')
        ->assertSee('Le serveur n’a pas pu enregistrer cette décision.')
        ->assertSee('Basile')
        ->click('Réessayer')
        ->assertPresent('[role="alert"]');
    $this->assertDatabaseCount('swipes', 0);
});

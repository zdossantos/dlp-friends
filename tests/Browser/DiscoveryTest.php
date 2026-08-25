<?php

use App\Actions\CreateSwipe;
use App\Enums\SwipeDecision;
use App\Models\Swipe;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

function discoveryMember(string $displayName): User
{
    $user = User::factory()->withProfile()->create();
    $user->profile?->update(['display_name' => $displayName]);

    return $user;
}

test('discovery renders its deferred empty state and limits the stack to five profiles', function () {
    $actor = discoveryMember('Alice');
    $this->actingAs($actor);

    visit('/discover')
        ->assertSee('Découvrir')
        ->assertSee('Vous avez exploré tous les profils disponibles')
        ->assertNoJavaScriptErrors();

    User::factory()->withProfile()->count(6)->create();

    visit('/discover')
        ->assertPresent('[aria-label="Profils à découvrir"]')
        ->assertCount('[data-test="discovery-card-stack-item"]', 5)
        ->assertScript(
            "[...document.querySelectorAll('[data-test=\"discovery-card-stack-item\"]')].slice(1).every((card) => card.ariaHidden === 'true' && card.inert)",
            true,
        )
        ->assertNoJavaScriptErrors();
});

test('discovery renders its loading state while suggestions are deferred', function () {
    Route::middleware(['web', 'auth'])->get(
        '/__browser/discovery-loading',
        fn () => Inertia::render('Discovery/Index', ['match' => null]),
    );
    $actor = discoveryMember('Alice');
    $this->actingAs($actor);

    visit('/__browser/discovery-loading')
        ->assertSee('Recherche de profils…')
        ->assertPresent('[aria-busy="true"]')
        ->assertNoJavaScriptErrors();
});

test('the top discovery card accepts keyboard and accessible decisions', function () {
    $actor = discoveryMember('Alice');
    $passed = discoveryMember('Basile');
    $this->actingAs($actor);

    $page = visit('/discover')
        ->assertPresent('[aria-label="Passer ce profil"]')
        ->assertPresent('[aria-label="Aimer ce profil"]')
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

test('pointer gestures follow the card and enforce the horizontal threshold', function () {
    $actor = discoveryMember('Alice');
    $target = discoveryMember('Basile');
    $this->actingAs($actor);

    $page = visit('/discover');
    $card = "document.querySelector('[data-test=\"discovery-card-stack-item\"] [tabindex=\"0\"]')";

    $page->script("{$card}.setPointerCapture = () => {}; {$card}.hasPointerCapture = () => false; {$card}.releasePointerCapture = () => {};");
    $page->script("{$card}.dispatchEvent(new PointerEvent('pointerdown', { pointerId: 1, clientX: 100, clientY: 100, bubbles: true })); {$card}.dispatchEvent(new PointerEvent('pointermove', { pointerId: 1, clientX: 171, clientY: 100, bubbles: true }));");
    $page->assertScript("{$card}.style.transform.includes('71px')", true);
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
    $page->script("{$card}.dispatchEvent(new PointerEvent('pointerdown', { pointerId: 7, clientX: 200, clientY: 100, bubbles: true })); {$card}.dispatchEvent(new PointerEvent('pointerup', { pointerId: 8, clientX: 100, clientY: 100, bubbles: true }));");
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
        ->click('Continuer à découvrir')
        ->assertNotPresent('[data-slot="dialog-title"]');

    $page->navigate('/discover')
        ->assertNotPresent('[data-slot="dialog-title"]');
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
    $failedOnce = false;
    Route::middleware(['web', 'auth'])->post('/discover/{target}/swipe', function (Request $request, User $target) use (&$failedOnce) {
        if (! $failedOnce) {
            $failedOnce = true;

            return back()->withErrors(['target' => 'Ce profil n’est pas disponible.']);
        }

        /** @var User $actor */
        $actor = $request->user();
        app(CreateSwipe::class)->handle(
            $actor,
            $target,
            SwipeDecision::from((string) $request->input('decision')),
        );

        return redirect('/discover');
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
    Route::middleware(['web', 'auth'])->post(
        '/discover/{target}/swipe',
        fn () => response('browser test failure', 500),
    );
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

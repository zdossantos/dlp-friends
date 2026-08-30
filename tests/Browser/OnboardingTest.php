<?php

use App\Enums\ProductOnboardingStatus;
use App\Enums\ProductOnboardingStep;
use App\Models\Avatar;
use App\Models\ProductOnboardingSetting;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

test('onboarding continues the registration stepper at the persisted tutorial step', function () {
    [$passAvatar, $likeAvatar] = Avatar::factory()->count(2)->create();
    ProductOnboardingSetting::query()->create([
        'id' => ProductOnboardingSetting::SINGLETON_ID,
        'pass_avatar_id' => $passAvatar->id,
        'like_avatar_id' => $likeAvatar->id,
    ]);
    foreach ([$passAvatar, $likeAvatar] as $avatar) {
        Storage::disk('local')->put($avatar->image_path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL8WQAAAABJRU5ErkJggg==',
        ));
    }
    $member = User::factory()->withProfile(false)->create();
    $member->productOnboarding()->create([
        'status' => ProductOnboardingStatus::InProgress,
        'step' => ProductOnboardingStep::LikeDemo,
    ]);
    $this->actingAs($member);

    visit('/onboarding')
        ->assertSee('6 sur 8')
        ->assertPresent('[aria-label="Étape 1 : Avatar"]')
        ->assertPresent('[aria-label="Étape 5 : Passer"]')
        ->assertPresent('[aria-label="Étape 6 : Découvrir"][aria-current="step"]')
        ->assertPresent('[aria-label="Étape 8 : Échange"]')
        ->assertNoJavaScriptErrors();
});

test('each swipe step disables and blocks the opposite decision', function () {
    [$passAvatar, $likeAvatar] = Avatar::factory()->count(2)->create();
    ProductOnboardingSetting::query()->create([
        'id' => ProductOnboardingSetting::SINGLETON_ID,
        'pass_avatar_id' => $passAvatar->id,
        'like_avatar_id' => $likeAvatar->id,
    ]);
    foreach ([$passAvatar, $likeAvatar] as $avatar) {
        Storage::disk('local')->put($avatar->image_path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL8WQAAAABJRU5ErkJggg==',
        ));
    }
    $member = User::factory()->withProfile(false)->create();
    $this->actingAs($member);

    $page = visit('/onboarding')
        ->assertPresent('[data-test="discovery-card"]')
        ->assertPresent('[aria-label="Découvrir ce profil"][disabled]')
        ->assertNotPresent('[aria-label="Passer ce profil"][disabled]');

    $page->script(<<<'JS'
        const card = document.querySelector('[data-test="discovery-card"]');
        card.dispatchEvent(new PointerEvent('pointerdown', { pointerId: 31, clientX: 100, clientY: 200, bubbles: true }));
        card.dispatchEvent(new PointerEvent('pointermove', { pointerId: 31, clientX: 220, clientY: 200, bubbles: true }));
    JS);
    $page->assertScript(
        "document.querySelector('[data-test=discovery-card]').style.transform.includes('translate3d(0px')",
        true,
    );
    $page->script(<<<'JS'
        const card = document.querySelector('[data-test="discovery-card"]');
        card.dispatchEvent(new PointerEvent('pointerup', { pointerId: 31, clientX: 220, clientY: 200, bubbles: true }));
        card.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }));
    JS);
    $page->assertSee('5 sur 8')
        ->click('[aria-label="Passer ce profil"]')
        ->assertSee('6 sur 8')
        ->assertPresent('[aria-label="Passer ce profil"][disabled]')
        ->assertNotPresent('[aria-label="Découvrir ce profil"][disabled]');

    $page->script(<<<'JS'
        const card = document.querySelector('[data-test="discovery-card"]');
        card.dispatchEvent(new PointerEvent('pointerdown', { pointerId: 32, clientX: 220, clientY: 200, bubbles: true }));
        card.dispatchEvent(new PointerEvent('pointermove', { pointerId: 32, clientX: 100, clientY: 200, bubbles: true }));
    JS);
    $page->assertScript(
        "document.querySelector('[data-test=discovery-card]').style.transform.includes('translate3d(0px')",
        true,
    );
    $page->script(<<<'JS'
        const card = document.querySelector('[data-test="discovery-card"]');
        card.dispatchEvent(new PointerEvent('pointerup', { pointerId: 32, clientX: 100, clientY: 200, bubbles: true }));
        card.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowLeft', bubbles: true }));
    JS);
    $page->assertSee('6 sur 8')->assertNoJavaScriptErrors();
});

test('onboarding uses the production match dialog without a discovery escape action', function () {
    [$passAvatar, $likeAvatar] = Avatar::factory()->count(2)->create();
    ProductOnboardingSetting::query()->create([
        'id' => ProductOnboardingSetting::SINGLETON_ID,
        'pass_avatar_id' => $passAvatar->id,
        'like_avatar_id' => $likeAvatar->id,
    ]);
    foreach ([$passAvatar, $likeAvatar] as $avatar) {
        Storage::disk('local')->put($avatar->image_path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL8WQAAAABJRU5ErkJggg==',
        ));
    }
    $member = User::factory()->withProfile(false)->create();
    $member->productOnboarding()->create([
        'status' => ProductOnboardingStatus::InProgress,
        'step' => ProductOnboardingStep::MatchDemo,
    ]);
    $this->actingAs($member);

    visit('/onboarding')
        ->assertSee('Vos univers se croisent')
        ->assertSee('Alex souhaite aussi te découvrir.')
        ->assertSee('Commencer l’échange')
        ->assertDontSee('Continuer à découvrir')
        ->assertNotPresent('[data-slot="dialog-close"]')
        ->assertNoJavaScriptErrors();
});

test('onboarding uses the production conversation interface and completes on send', function () {
    [$passAvatar, $likeAvatar] = Avatar::factory()->count(2)->create();
    ProductOnboardingSetting::query()->create([
        'id' => ProductOnboardingSetting::SINGLETON_ID,
        'pass_avatar_id' => $passAvatar->id,
        'like_avatar_id' => $likeAvatar->id,
    ]);
    foreach ([$passAvatar, $likeAvatar] as $avatar) {
        Storage::disk('local')->put($avatar->image_path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL8WQAAAABJRU5ErkJggg==',
        ));
    }
    $member = User::factory()->withProfile(false)->create();
    $member->productOnboarding()->create([
        'status' => ProductOnboardingStatus::InProgress,
        'step' => ProductOnboardingStep::ConversationDemo,
    ]);
    $this->actingAs($member);

    visit('/onboarding')
        ->assertSee('Alex')
        ->assertSee('Échange privé')
        ->assertPresent('[role="log"][aria-label="Historique des messages"]')
        ->assertPresent('#message-content[placeholder="Écris un message…"]')
        ->assertDontSee('fictif')
        ->type('#message-content', 'Bonjour !')
        ->click('[aria-label="Envoyer le message"]')
        ->assertPathIs('/discover')
        ->assertNoJavaScriptErrors();

    expect($member->productOnboarding()->firstOrFail()->status)
        ->toBe(ProductOnboardingStatus::Completed);
    $this->assertDatabaseCount('matches', 0);
    $this->assertDatabaseCount('conversations', 0);
    $this->assertDatabaseCount('messages', 0);
});

test('member completes the mandatory onboarding without escape actions or social writes', function () {
    [$passAvatar, $likeAvatar] = Avatar::factory()->count(2)->create();
    ProductOnboardingSetting::query()->create([
        'id' => ProductOnboardingSetting::SINGLETON_ID,
        'pass_avatar_id' => $passAvatar->id,
        'like_avatar_id' => $likeAvatar->id,
    ]);
    foreach ([$passAvatar, $likeAvatar] as $avatar) {
        Storage::disk('local')->put($avatar->image_path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL8WQAAAABJRU5ErkJggg==',
        ));
    }
    $member = User::factory()->withProfile(false)->create();
    $this->actingAs($member);

    $page = visit('/onboarding')
        ->assertNotPresent('[data-test="member-bottom-navigation"]')
        ->assertDontSee('Continuer plus tard')
        ->assertDontSee('Ignorer le tutoriel')
        ->assertDontSee('Recommencer')
        ->assertDontSee('Démonstration')
        ->assertDontSee('fictif')
        ->assertSee('Pour découvrir comment écarter un profil, choisis Passer.')
        ->assertPresent('[aria-label="Découvrir ce profil"][disabled]')
        ->click('[aria-label="Passer ce profil"]');

    $page->assertNoJavaScriptErrors()
        ->assertSee('Choisis Découvrir pour indiquer que tu souhaites faire connaissance.')
        ->assertPresent('[aria-label="Passer ce profil"][disabled]')
        ->click('[aria-label="Découvrir ce profil"]')
        ->assertSee('Vos univers se croisent')
        ->assertSee('Lorsque deux membres choisissent Découvrir, leurs univers se croisent.')
        ->click('[data-test="open-match-conversation"]')
        ->assertSee('Échange privé')
        ->assertSee('Envoie un premier message pour terminer ton inscription.')
        ->type('#message-content', 'Bonjour !')
        ->click('[aria-label="Envoyer le message"]')
        ->assertPathIs('/discover')
        ->assertNoJavaScriptErrors();

    expect($member->productOnboarding()->firstOrFail()->status)
        ->toBe(ProductOnboardingStatus::Completed);
    $this->assertDatabaseCount('swipes', 0);
    $this->assertDatabaseCount('matches', 0);
    $this->assertDatabaseCount('conversations', 0);
    $this->assertDatabaseCount('messages', 0);
});

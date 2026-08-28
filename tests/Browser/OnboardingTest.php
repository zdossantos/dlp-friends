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
    $member = User::factory()->withProfile()->create();
    $member->productOnboarding()->create([
        'status' => ProductOnboardingStatus::InProgress,
        'step' => ProductOnboardingStep::LikeDemo,
    ]);
    $this->actingAs($member);

    visit('/onboarding')
        ->assertSee('6 sur 8')
        ->assertPresent('[aria-label="Étape 1 : Avatar"]')
        ->assertPresent('[aria-label="Étape 5 : Passer"]')
        ->assertPresent('[aria-label="Étape 6 : J’aime"][aria-current="step"]')
        ->assertPresent('[aria-label="Étape 8 : Discussion"]')
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
    $member = User::factory()->withProfile()->create();
    $this->actingAs($member);

    $page = visit('/onboarding')
        ->assertPresent('[data-test="discovery-card"]')
        ->assertPresent('[aria-label="Aimer ce profil"][disabled]')
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
        ->assertNotPresent('[aria-label="Aimer ce profil"][disabled]');

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
    $member = User::factory()->withProfile()->create();
    $member->productOnboarding()->create([
        'status' => ProductOnboardingStatus::InProgress,
        'step' => ProductOnboardingStep::MatchDemo,
    ]);
    $this->actingAs($member);

    visit('/onboarding')
        ->assertSee('C’est un match !')
        ->assertSee('Alex a aussi aimé votre profil.')
        ->assertSee('Ouvrir la conversation')
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
    $member = User::factory()->withProfile()->create();
    $member->productOnboarding()->create([
        'status' => ProductOnboardingStatus::InProgress,
        'step' => ProductOnboardingStep::ConversationDemo,
    ]);
    $this->actingAs($member);

    visit('/onboarding')
        ->assertSee('Alex')
        ->assertSee('Échange privé')
        ->assertPresent('[role="log"][aria-label="Historique des messages"]')
        ->assertPresent('#message-content[placeholder="Écrire un message…"]')
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

test('member completes the forced demo journey without real social writes', function () {
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
    $member = User::factory()->withProfile()->create();
    $this->actingAs($member);

    $page = visit('/onboarding')
        ->assertSee('Démonstration')
        ->assertMissing('[data-test="member-bottom-navigation"]')
        ->assertSee('Continuer plus tard')
        ->assertSee('Cette démonstration est entièrement fictive')
        ->assertSee('Pour découvrir comment écarter un profil qui ne vous correspond pas, choisissez Passer.')
        ->assertDontSee('Quitter')
        ->assertScript(
            "document.querySelector('[data-test=demo-swipe-card]').getBoundingClientRect().width >= 500",
            true,
        )
        ->click('[data-test="demo-like"]')
        ->assertSee('Cette étape vous demande de passer ce profil.')
        ->click('[data-test="demo-pass"]');

    $page->assertNoJavaScriptErrors()
        ->assertSee('Aimez ce profil pour indiquer que vous souhaitez faire connaissance.')
        ->click('[data-test="demo-pass"]')
        ->assertSee('Cette étape vous demande d’aimer ce profil.')
        ->click('[data-test="demo-like"]')
        ->assertSee('Match de démonstration')
        ->assertSee('Lorsque deux membres s’aiment mutuellement, un match amical est créé.')
        ->click('[data-test="open-demo-conversation"]')
        ->assertSee('Conversation de démonstration')
        ->assertSee('Envoyez une réponse fictive pour terminer le tutoriel.')
        ->type('[data-test="demo-message-input"]', 'Bonjour !')
        ->click('[data-test="send-demo-message"]')
        ->assertSee('Bonjour !')
        ->click('[data-test="complete-demo-conversation"]')
        ->assertPathIs('/onboarding')
        ->assertSee('Vous êtes prêt ! Découvrez maintenant de vrais profils.')
        ->click('[data-test="discover-real-profiles"]')
        ->assertPathIs('/discover')
        ->assertNoJavaScriptErrors();

    expect($member->productOnboarding()->firstOrFail()->status)
        ->toBe(ProductOnboardingStatus::Completed);
    $this->assertDatabaseCount('swipes', 0);
    $this->assertDatabaseCount('matches', 0);
    $this->assertDatabaseCount('conversations', 0);
    $this->assertDatabaseCount('messages', 0);
});

test('member resumes, uses the keyboard, restarts and skips the demo', function () {
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
    $member = User::factory()->withProfile()->create();
    $member->productOnboarding()->create([
        'status' => ProductOnboardingStatus::InProgress,
        'step' => ProductOnboardingStep::LikeDemo,
    ]);
    $this->actingAs($member);

    $page = visit('/settings/onboarding')
        ->assertSee('Reprendre le tutoriel')
        ->assertSee('Recommencer depuis le début')
        ->click('Reprendre le tutoriel')
        ->assertSee('Aimez ce profil pour indiquer que vous souhaitez faire connaissance.')
        ->assertSee('Continuer plus tard')
        ->click('[data-test="demo-pass"]')
        ->assertSee('Cette étape vous demande d’aimer ce profil.')
        ->keys('[data-test="demo-swipe-card"]', 'ArrowRight')
        ->assertSee('Match de démonstration')
        ->assertScript(
            "document.activeElement === document.querySelector('[data-test=demo-match-heading]')",
            true,
        )
        ->click('Recommencer')
        ->assertSee('Pour découvrir comment écarter un profil qui ne vous correspond pas, choisissez Passer.')
        ->click('Ignorer le tutoriel')
        ->assertSee('Quitter le tutoriel maintenant ?')
        ->assertSee('Continuer plus tard conserve votre progression')
        ->assertPathIs('/onboarding')
        ->click('Annuler')
        ->assertPathIs('/onboarding')
        ->click('Ignorer le tutoriel')
        ->click('[data-test="confirm-skip-onboarding"]')
        ->assertPathIs('/discover')
        ->assertNoJavaScriptErrors();

    expect($member->productOnboarding()->firstOrFail()->status)
        ->toBe(ProductOnboardingStatus::Skipped);
    $this->assertDatabaseCount('swipes', 0);
    $this->assertDatabaseCount('matches', 0);
    $this->assertDatabaseCount('conversations', 0);
    $this->assertDatabaseCount('messages', 0);
});

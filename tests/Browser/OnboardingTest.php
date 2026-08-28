<?php

use App\Enums\ProductOnboardingStatus;
use App\Enums\ProductOnboardingStep;
use App\Models\Avatar;
use App\Models\ProductOnboardingSetting;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

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
        ->assertDontSee('Quitter')
        ->assertScript(
            "document.querySelector('[data-test=demo-swipe-card]').getBoundingClientRect().width >= 500",
            true,
        )
        ->click('[data-test="demo-like"]')
        ->assertSee('Cette étape vous demande de passer ce profil.')
        ->click('[data-test="demo-pass"]');

    $page->assertNoJavaScriptErrors()
        ->assertSee('Indiquez maintenant votre intérêt.')
        ->click('[data-test="demo-pass"]')
        ->assertSee('Cette étape vous demande d’aimer ce profil.')
        ->click('[data-test="demo-like"]')
        ->assertSee('Match de démonstration')
        ->assertSee('Ouvrez ce match fictif pour découvrir la conversation.')
        ->click('[data-test="open-demo-conversation"]')
        ->assertSee('Conversation de démonstration')
        ->assertSee('Envoyez une réponse fictive pour terminer le tutoriel.')
        ->type('[data-test="demo-message-input"]', 'Bonjour !')
        ->click('[data-test="send-demo-message"]')
        ->assertSee('Bonjour !')
        ->click('[data-test="complete-demo-conversation"]')
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
        ->assertSee('Indiquez maintenant votre intérêt.')
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
        ->assertSee('Pour commencer, passez cette carte.')
        ->click('Ignorer le tutoriel')
        ->assertPathIs('/discover')
        ->assertNoJavaScriptErrors();

    expect($member->productOnboarding()->firstOrFail()->status)
        ->toBe(ProductOnboardingStatus::Skipped);
    $this->assertDatabaseCount('swipes', 0);
    $this->assertDatabaseCount('matches', 0);
    $this->assertDatabaseCount('conversations', 0);
    $this->assertDatabaseCount('messages', 0);
});

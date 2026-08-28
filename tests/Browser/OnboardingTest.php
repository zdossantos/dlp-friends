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
        ->click('[data-test="demo-like"]')
        ->assertSee('Pour commencer, passez cette carte.')
        ->click('[data-test="demo-pass"]');

    $page->assertNoJavaScriptErrors()
        ->assertSee('Indiquez maintenant votre intérêt.')
        ->click('[data-test="demo-like"]')
        ->assertSee('Match de démonstration')
        ->click('[data-test="open-demo-conversation"]')
        ->assertSee('Conversation de démonstration')
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

    $page = visit('/onboarding')
        ->assertSee('Indiquez maintenant votre intérêt.')
        ->click('[data-test="demo-pass"]')
        ->assertSee('Indiquez maintenant votre intérêt.')
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

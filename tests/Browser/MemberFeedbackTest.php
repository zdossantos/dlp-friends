<?php

use App\Models\MemberFeedback;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function feedbackBrowserAvatar(User $member): void
{
    Storage::disk('local')->put($member->profile->avatar->image_path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL8WQAAAABJRU5ErkJggg=='));
}

test('members submit feedback from a compact profile drawer and regain trigger focus', function (string $device, string $locale) {
    $member = User::factory()->withProfile()->create(['locale' => $locale]);
    feedbackBrowserAvatar($member);
    $this->actingAs($member);
    $page = visit('/profile')->on()->{$device}()->assertPresent('[data-test="feedback-trigger"]');
    $page->click('[data-test="feedback-trigger"]')
        ->assertPresent('[data-test="feedback-drawer"]')
        ->assertAttribute('#feedback-content', 'maxlength', '5000')
        ->assertScript("document.querySelectorAll('[data-test=feedback-drawer] select').length", 2)
        ->assertScript("document.querySelector('[data-test=feedback-drawer] input[type=file]') === null", true)
        ->select('#feedback-kind', 'problem')->select('#feedback-area', 'messaging')
        ->fill('#feedback-content', 'Le problème décrit par un membre.')
        ->click('[data-test="feedback-submit"]')
        ->assertPresent('[data-test="feedback-success"]')
        ->assertNoJavaScriptErrors()
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
    $feedback = MemberFeedback::query()->sole();
    expect($feedback->user_id)->toBe($member->id)->and($feedback->kind->value)->toBe('problem')->and($feedback->status->value)->toBe('pending');
    $page->keys('[data-test="feedback-close"]', 'Escape');
    $page->assertNotPresent('[data-test="feedback-drawer"]')
        ->assertScript("document.activeElement?.matches('[data-test=feedback-trigger]')", true);
})->with([['mobile', 'fr'], ['desktop', 'en']]);

test('server validation keeps the feedback drawer and draft open for correction', function () {
    $member = User::factory()->withProfile()->create();
    feedbackBrowserAvatar($member);
    $this->actingAs($member);
    $page = visit('/profile')->on()->mobile()->assertPresent('[data-test="feedback-trigger"]');
    $page->click('[data-test="feedback-trigger"]')->select('#feedback-kind', 'problem')->select('#feedback-area', 'trips')
        ->fill('#feedback-content', '   ')->click('[data-test="feedback-submit"]')
        ->assertScript("document.querySelector('#feedback-content-error')?.textContent.trim().length > 0", true)->assertValue('#feedback-content', '   ')
        ->assertValue('#feedback-kind', 'problem')->assertValue('#feedback-area', 'trips')
        ->fill('#feedback-content', 'Une explication corrigée.')->click('[data-test="feedback-submit"]')
        ->assertPresent('[data-test="feedback-success"]')->assertNoJavaScriptErrors();
    expect(MemberFeedback::query()->sole()->content)->toBe('Une explication corrigée.');
});

test('admins filter feedback and save a private note on mobile', function () {
    $member = User::factory()->withProfile()->create();
    $feedback = MemberFeedback::query()->create(['user_id' => $member->id, 'kind' => 'idea', 'area' => 'profile', 'content' => 'Une idée pour mon profil.']);
    MemberFeedback::query()->create(['user_id' => $member->id, 'kind' => 'problem', 'area' => 'trips', 'content' => 'Autre retour.']);
    $this->actingAs(User::factory()->admin()->create());
    $page = visit('/admin/feedback')->on()->mobile()->assertPresent('#feedback-filter-kind');
    $page->select('#feedback-filter-kind', 'idea')->click('[data-test="feedback-filter-submit"]')
        ->assertPresent('[data-test="feedback-item-'.$feedback->id.'"]')
        ->click('[data-test="feedback-item-'.$feedback->id.'"]')
        ->assertSee('Une idée pour mon profil.')
        ->select('#feedback-status', 'accepted')->fill('#feedback-note', 'À examiner en équipe.')
        ->click('[data-test="feedback-save"]')->assertPresent('[data-test="feedback-saved"]')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)->assertNoJavaScriptErrors();
    expect($feedback->fresh()->status->value)->toBe('accepted')->and($feedback->fresh()->internal_note)->toBe('À examiner en équipe.');
});

test('a throttled feedback keeps the draft and shows an inline retry message', function () {
    $member = User::factory()->withProfile()->create();
    feedbackBrowserAvatar($member);
    $this->actingAs($member);
    for ($i = 0; $i < 5; $i++) {
        $this->post('/feedback', ['kind' => 'idea', 'area' => 'profile', 'content' => ''])->assertSessionHasErrors('content');
    }
    $page = visit('/profile')->on()->mobile()->assertPresent('[data-test="feedback-trigger"]');
    $page->click('[data-test="feedback-trigger"]')->select('#feedback-area', 'profile')
        ->fill('#feedback-content', 'Un brouillon à conserver.')->click('[data-test="feedback-submit"]')
        ->assertSee(__('feedback.throttled'))->assertValue('#feedback-content', 'Un brouillon à conserver.')
        ->assertValue('#feedback-area', 'profile')->assertPresent('[data-test="feedback-drawer"]')->assertNoJavaScriptErrors();
    expect(MemberFeedback::query()->count())->toBe(0);
});

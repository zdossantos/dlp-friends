<?php

use App\Actions\ReportConversation;
use App\Enums\ConversationReportReason;
use App\Models\MemberMatch;
use App\Models\User;
use App\Support\BannedAuthentication;
use Illuminate\Support\Facades\Storage;

it('offers blocking by default and cancels without reporting on desktop and mobile', function (string $device) {
    Storage::fake('local');
    $member = User::factory()->withProfile()->create();
    $peer = User::factory()->withProfile()->create();
    foreach ([$member, $peer] as $user) {
        Storage::disk('local')->put($user->profile->avatar->image_path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL8WQAAAABJRU5ErkJggg=='));
    }
    $conversation = MemberMatch::query()->create(['user_low_id' => $member->id, 'user_high_id' => $peer->id])->conversation()->create();
    $this->actingAs($member);
    $page = visit("/conversations/{$conversation->id}")->on()->{$device}();
    $page->click('[data-test="report-conversation-trigger"]')
        ->assertSee('Toute la conversation')
        ->assertScript("document.querySelector('#report-block-yes').checked", true)
        ->click('[data-test="cancel-report"]')
        ->assertNotPresent('#report-reason')
        ->assertNoJavaScriptErrors();
    $this->assertDatabaseCount('conversation_reports', 0);
    $this->assertDatabaseCount('blocks', 0);
})->with(['desktop', 'mobile']);

it('submits the chosen blocking option and keeps an administrator reportable', function (string $targetRole, bool $block) {
    Storage::fake('local');
    $member = User::factory()->withProfile()->create();
    $peer = $targetRole === 'admin' ? User::factory()->withProfile()->admin()->create() : User::factory()->withProfile()->create();
    foreach ([$member, $peer] as $user) {
        Storage::disk('local')->put($user->profile->avatar->image_path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL8WQAAAABJRU5ErkJggg=='));
    }
    $conversation = MemberMatch::query()->create(['user_low_id' => $member->id, 'user_high_id' => $peer->id])->conversation()->create();
    $this->actingAs($member);
    $page = visit("/conversations/{$conversation->id}")->click('[data-test="report-conversation-trigger"]')->select('#report-reason', 'other');
    if ($targetRole === 'admin') {
        $page->assertNotPresent('#report-block-yes');
    } elseif (! $block) {
        $page->click('#report-block-no');
    }
    $page->fill('#report-details', 'Contexte')->click('[data-test="confirm-report"]')->assertPathIs('/conversations')->assertNoJavaScriptErrors();
    $this->assertDatabaseCount('conversation_reports', 1);
    $this->assertDatabaseCount('blocks', $block ? 1 : 0);
})->with([['user', true], ['user', false], ['admin', false]]);

it('lets the admin read close ban and lift a ban from the review page', function (string $device) {
    $reporter = User::factory()->withProfile()->create();
    $target = User::factory()->withProfile()->create();
    $admin = User::factory()->withProfile()->admin()->create();
    $conversation = MemberMatch::query()->create(['user_low_id' => $reporter->id, 'user_high_id' => $target->id])->conversation()->create();
    $conversation->messages()->create(['author_user_id' => $target->id, 'content' => 'Message à examiner']);
    $report = app(ReportConversation::class)->handle($reporter, $conversation, ConversationReportReason::Other, null, false);
    $this->actingAs($admin);
    $page = visit("/admin/conversation-reports/{$report->id}")->on()->{$device}()->assertSee('Message à examiner');
    $page->assertNotPresent('[data-test="member-ban-trigger"]')
        ->assertScript("document.querySelector('[data-test=\"review-member-ban\"]').parentElement === document.querySelector('[data-test=\"close-report\"]').parentElement", true)
        ->click('[data-test="review-member-ban"]');
    expect($target->fresh()->status->value)->toBe('active');
    $page->fill('#close-decision', 'Sanction motivée')->click('[data-test="review-member-ban"]')->assertSee('Lever le bannissement');
    $this->assertDatabaseHas('moderation_audits', ['target_user_id' => $target->id, 'operation' => 'ban', 'reason' => 'Sanction motivée']);
    expect($target->fresh()->status->value)->toBe('banned');
    $page->fill('#close-decision', 'Réexamen')->click('[data-test="review-member-ban"]')->assertSee('Bannir');
    expect($target->fresh()->status->value)->toBe('active');
    $page->fill('#close-decision', 'Examen terminé')->click('[data-test="close-report"]')->assertSee('Examen terminé')->assertNotPresent('[data-test="close-report"]')->assertNoJavaScriptErrors();
    expect($report->fresh()->closed_at)->not->toBeNull();
    $page->fill('#close-decision', 'Sanction après clôture')->click('[data-test="review-member-ban"]')->assertSee('Lever le bannissement')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNotPresent('[role="dialog"]');
    $this->assertDatabaseHas('moderation_audits', ['target_user_id' => $target->id, 'operation' => 'ban', 'reason' => 'Sanction après clôture']);
})->with(['desktop', 'mobile']);

it('requires an initially unchecked explicit acceptance before continuing', function () {
    $member = User::factory()->withProfile()->create();
    $member->termsAcceptances()->delete();
    $this->actingAs($member);
    $page = visit('/discover')->assertPathIs('/terms/accept')->assertAttribute('#accept-current-terms', 'data-state', 'unchecked');
    $page->click('#accept-current-terms')->click('Accepter et continuer')->assertPathIs('/discover')->assertNoJavaScriptErrors();
    expect($member->termsAcceptances()->where('terms_version', config('legal.terms.version'))->exists())->toBeTrue();
});

it('shows the ban explanation on login after a valid recovery code', function () {
    $user = User::factory()->withTwoFactor()->create(['status' => 'banned', 'locale' => 'fr']);
    visit('/login')->click('[data-test="locale-fr"]')->assertSee('Heureux de te revoir')->fill('#email', $user->email)->fill('#password', 'password')
        ->press('[data-test="login-button"]')->assertPathIs('/two-factor-challenge')
        ->click('utiliser un code de récupération')->fill('[name="recovery_code"]', 'recovery-code-1')
        ->press('Continuer')->assertPathIs('/login')
        ->assertSee(BannedAuthentication::message())->assertNoJavaScriptErrors();
    $this->assertGuest();
});

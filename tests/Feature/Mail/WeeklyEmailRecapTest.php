<?php

use App\Actions\BuildUserDataExport;
use App\Actions\BuildWeeklyEmailRecap;
use App\Enums\ProfileVisibility;
use App\Enums\UserStatus;
use App\Jobs\PurgeDeletedUser;
use App\Jobs\SendWeeklyEmailRecap;
use App\Mail\WeeklyEmailRecapMail;
use App\Models\Block;
use App\Models\MemberMatch;
use App\Models\Message;
use App\Models\User;
use App\Models\WeeklyEmailRecapDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-11 15:00', 'Europe/Paris'));
    $this->periodEnd = CarbonImmutable::now()->utc();
    $this->member = User::factory()->withProfile()->create(['locale' => 'fr']);
    $this->other = User::factory()->withProfile()->create();
    $match = MemberMatch::query()->create([
        'user_low_id' => $this->member->id,
        'user_high_id' => $this->other->id,
    ]);
    $match->forceFill(['created_at' => $this->periodEnd->subHour()])->save();
    $this->conversation = $match->conversation()->create();
    $this->message = Message::factory()->create([
        'conversation_id' => $this->conversation->id,
        'author_user_id' => $this->other->id,
        'content' => 'Contenu privé jamais envoyé par email',
        'created_at' => $this->periodEnd->subDays(3)->subSecond(),
    ]);
});

function recapSummaryFor(object $context): ?array
{
    return app(BuildWeeklyEmailRecap::class)->handle($context->member->fresh(), $context->periodEnd);
}

function reserveRecapFor(object $context): WeeklyEmailRecapDelivery
{
    return WeeklyEmailRecapDelivery::query()->create([
        'user_id' => $context->member->id,
        'period_ends_at' => $context->periodEnd,
    ]);
}

test('weekly recap counts conversations rather than messages and recent matches', function () {
    Message::factory()->create(['conversation_id' => $this->conversation->id, 'author_user_id' => $this->other->id]);
    expect(recapSummaryFor($this))->toBe(['conversations' => 1, 'matches' => 1]);
});

test('the old unread threshold is strictly more than three days', function (string $age, bool $eligible) {
    $this->message->forceFill(['created_at' => $this->periodEnd->modify($age)])->save();
    expect(recapSummaryFor($this) !== null)->toBe($eligible);
})->with([
    ['-3 days -1 second', true],
    ['-3 days', false],
    ['-2 days', false],
]);

test('sent or read messages never trigger recap even with a new match', function (string $kind) {
    $this->message->forceFill($kind === 'read' ? ['read_at' => now()] : ['author_user_id' => $this->member->id])->save();
    expect(recapSummaryFor($this))->toBeNull();
})->with(['read', 'sent']);

test('recap excludes unavailable recipients and conversations', function (string $reason) {
    switch ($reason) {
        case 'hidden': $this->other->profile->update(['visibility' => ProfileVisibility::Hidden]);
            break;
        case 'blocked-by-recipient': Block::query()->create(['blocker_user_id' => $this->member->id, 'blocked_user_id' => $this->other->id]);
            break;
        case 'blocked-by-other': Block::query()->create(['blocker_user_id' => $this->other->id, 'blocked_user_id' => $this->member->id]);
            break;
        case 'archived': $this->conversation->update(['archived_at' => now()]);
            break;
        case 'banned': $this->member->forceFill(['status' => UserStatus::Banned])->save();
            break;
        case 'pending-deletion': $this->member->forceFill(['status' => UserStatus::PendingDeletion])->save();
            break;
        case 'deletion-date': $this->member->forceFill(['deletion_requested_at' => now()])->save();
            break;
        case 'unverified': $this->member->forceFill(['email_verified_at' => null])->save();
            break;
        case 'invalid-email': $this->member->forceFill(['email' => 'invalid'])->save();
            break;
        case 'other-inactive': $this->other->forceFill(['status' => UserStatus::Banned])->save();
            break;
        case 'disabled': $this->member->forceFill(['weekly_recap_messages' => false])->save();
            break;
        case 'no-member-role': $this->member->roles()->detach();
            break;
    }
    expect(recapSummaryFor($this))->toBeNull();
})->with(['hidden', 'blocked-by-recipient', 'blocked-by-other', 'archived', 'banned', 'pending-deletion', 'deletion-date', 'unverified', 'invalid-email', 'other-inactive', 'disabled', 'no-member-role']);

test('a member with their own hidden profile still receives visible conversation reminders', function () {
    $this->member->profile->update(['visibility' => ProfileVisibility::Hidden]);
    expect(recapSummaryFor($this))->toBe(['conversations' => 1, 'matches' => 1]);
});

test('weekly match counts respect period boundaries and exclude unavailable pairs', function () {
    $start = $this->periodEnd->setTimezone('Europe/Paris')->subWeek()->utc();
    $this->conversation->memberMatch->forceFill(['created_at' => $start])->save();
    expect(recapSummaryFor($this)['matches'])->toBe(1);
    $this->conversation->memberMatch->forceFill(['created_at' => $start->subSecond()])->save();
    expect(recapSummaryFor($this)['matches'])->toBe(0);
    $this->conversation->memberMatch->forceFill(['created_at' => $this->periodEnd])->save();
    expect(recapSummaryFor($this)['matches'])->toBe(0);
    $third = User::factory()->withProfile()->create();
    $extra = MemberMatch::query()->create(['user_low_id' => $this->member->id, 'user_high_id' => $third->id]);
    $extra->conversation()->create();
    $extra->forceFill(['created_at' => $this->periodEnd->subHour()])->save();
    expect(recapSummaryFor($this)['matches'])->toBe(1);
    $third->profile->update(['visibility' => ProfileVisibility::Hidden]);
    expect(recapSummaryFor($this)['matches'])->toBe(0);
    $third->profile->update(['visibility' => ProfileVisibility::Visible]);
    Block::query()->create(['blocker_user_id' => $third->id, 'blocked_user_id' => $this->member->id]);
    expect(recapSummaryFor($this)['matches'])->toBe(0);
});

test('the match counter can be disabled without disabling the reminder', function () {
    $this->member->forceFill(['weekly_recap_matches' => false])->save();
    expect(recapSummaryFor($this))->toBe(['conversations' => 1, 'matches' => null]);
});

test('dispatching twice reserves and queues at most one delivery for the period', function () {
    Queue::fake();
    $this->artisan('notifications:dispatch-weekly-recaps')->assertSuccessful();
    $this->artisan('notifications:dispatch-weekly-recaps')->assertSuccessful();
    expect(WeeklyEmailRecapDelivery::query()->count())->toBe(1);
    Queue::assertPushed(SendWeeklyEmailRecap::class, 1);
    $this->travelTo($this->periodEnd->setTimezone('Europe/Paris')->addWeek());
    $this->artisan('notifications:dispatch-weekly-recaps')->assertSuccessful();
    expect(WeeklyEmailRecapDelivery::query()->count())->toBe(2);
    Queue::assertPushed(SendWeeklyEmailRecap::class, 2);
});

test('ineligible members are not queued and no mail is sent before the first Sunday deadline', function () {
    Queue::fake();
    $this->message->forceFill(['read_at' => now()])->save();
    $this->artisan('notifications:dispatch-weekly-recaps')->assertSuccessful();
    Queue::assertNothingPushed();
    $this->message->forceFill(['read_at' => null])->save();
    $this->travelTo($this->periodEnd->subSecond());
    $this->artisan('notifications:dispatch-weekly-recaps')->assertSuccessful();
    Queue::assertNothingPushed();
});

test('the database rejects concurrent duplicate reservations', function () {
    reserveRecapFor($this);
    expect(fn () => reserveRecapFor($this))->toThrow(UniqueConstraintViolationException::class);
});

test('a queued recap is sent once in the current member language', function () {
    Mail::fake();
    $delivery = reserveRecapFor($this);
    $this->member->forceFill(['locale' => 'en'])->save();
    $job = new SendWeeklyEmailRecap($delivery->id);
    $job->handle(app(BuildWeeklyEmailRecap::class));
    $job->handle(app(BuildWeeklyEmailRecap::class));
    Mail::assertSent(WeeklyEmailRecapMail::class, fn ($mail) => $mail->hasTo($this->member->email) && $mail->locale === 'en' && $mail->conversations === 1);
    Mail::assertSentCount(1);
    expect($delivery->fresh()->sent_at)->not->toBeNull();
});

test('queued recaps recheck eligibility and skip expired or unavailable deliveries', function (string $change) {
    Mail::fake();
    $delivery = reserveRecapFor($this);
    switch ($change) {
        case 'read': $this->message->forceFill(['read_at' => now()])->save();
            break;
        case 'disabled': $this->member->forceFill(['weekly_recap_messages' => false])->save();
            break;
        case 'hidden': $this->other->profile->update(['visibility' => ProfileVisibility::Hidden]);
            break;
        case 'blocked': Block::query()->create(['blocker_user_id' => $this->other->id, 'blocked_user_id' => $this->member->id]);
            break;
        case 'inactive': $this->member->forceFill(['status' => UserStatus::Banned])->save();
            break;
        case 'invalid-email': $this->member->forceFill(['email' => 'invalid'])->save();
            break;
        case 'unverified': $this->member->forceFill(['email_verified_at' => null])->save();
            break;
        case 'deleted': $this->member->delete();
            break;
        case 'expired': $this->travelTo($this->periodEnd->setTimezone('Europe/Paris')->addWeek());
            break;
    }
    $job = new SendWeeklyEmailRecap($delivery->id);
    $job->handle(app(BuildWeeklyEmailRecap::class));
    $job->handle(app(BuildWeeklyEmailRecap::class));
    Mail::assertNothingSent();
    if ($change !== 'deleted') {
        expect($delivery->fresh()->skipped_at)->not->toBeNull();
    } else {
        expect($delivery->fresh())->toBeNull();
    }
})->with(['read', 'disabled', 'hidden', 'blocked', 'inactive', 'invalid-email', 'unverified', 'deleted', 'expired']);

test('queued recaps recompute counts and the optional match section', function () {
    Mail::fake();
    $delivery = reserveRecapFor($this);
    $this->member->forceFill(['weekly_recap_matches' => false])->save();
    (new SendWeeklyEmailRecap($delivery->id))->handle(app(BuildWeeklyEmailRecap::class));
    Mail::assertSent(WeeklyEmailRecapMail::class, fn ($mail) => $mail->matches === null);
});

test('transport failure leaves a delivery retryable until a successful send', function () {
    $delivery = reserveRecapFor($this);
    $job = new SendWeeklyEmailRecap($delivery->id);
    $mailManager = Mail::getFacadeRoot();
    Mail::shouldReceive('to')->once()->with($this->member->email)->andReturnSelf();
    Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('Temporary SMTP failure'));
    expect(fn () => $job->handle(app(BuildWeeklyEmailRecap::class)))->toThrow(RuntimeException::class);
    expect($delivery->fresh()->sent_at)->toBeNull()->and($delivery->fresh()->skipped_at)->toBeNull();
    Mail::swap($mailManager);
    Mail::fake();
    $job->handle(app(BuildWeeklyEmailRecap::class));
    Mail::assertSentCount(1);
    expect($delivery->fresh()->sent_at)->not->toBeNull();
});

test('weekly mail renders localized counts and authenticated internal links without private data', function (string $locale, string $subject, string $conversations, string $matches) {
    $mail = (new WeeklyEmailRecapMail(2, 3))->locale($locale);
    $mail->assertHasSubject($subject);
    $mail->assertSeeInHtml($conversations);
    $mail->assertSeeInText($matches);
    $mail->assertSeeInHtml(route('notification-preferences.edit'));
    $mail->assertSeeInHtml(url('/app'));
    $mail->assertDontSeeInHtml($this->message->content);
    $mail->assertDontSeeInHtml($this->other->profile->display_name);
    $withoutMatches = (new WeeklyEmailRecapMail(2, null))->locale($locale);
    $withoutMatches->assertDontSeeInHtml($matches);
})->with([
    ['fr', 'Ton récapitulatif hebdomadaire DLP Friends', '2 conversations avec des messages non lus', '3 nouveaux univers croisés cette semaine'],
    ['en', 'Your DLP Friends weekly recap', '2 conversations with unread messages', '3 new connections this week'],
]);

test('weekly schedule runs on Sunday at 15 Paris time across clock changes', function (string $time, bool $due) {
    $this->travelTo(CarbonImmutable::parse($time, 'Europe/Paris'));
    $events = app(Schedule::class)->events();
    $recap = collect($events)->first(fn ($event) => str_contains($event->command ?? '', 'notifications:dispatch-weekly-recaps'));
    expect($recap)->not->toBeNull()->and($recap->isDue(app()))->toBe($due);
})->with([
    ['2026-10-11 14:59', false], ['2026-10-11 15:00', true], ['2026-10-12 15:00', false],
    ['2026-03-29 15:00', true], ['2026-10-25 15:00', true],
]);

test('recap delivery metadata is exported and removed with the account', function () {
    $delivery = reserveRecapFor($this);
    $export = app(BuildUserDataExport::class)->handle($this->member);
    expect($export['weekly_email_recap_deliveries'])->toHaveCount(1)
        ->and($export['weekly_email_recap_deliveries'][0]['sent_at'])->toBeNull();
    $this->member->delete();
    expect($delivery->fresh())->toBeNull();
});

test('the recap includes newer unread conversations once an old message triggers it', function () {
    $third = User::factory()->withProfile()->create();
    $match = MemberMatch::query()->create(['user_low_id' => $this->member->id, 'user_high_id' => $third->id]);
    $conversation = $match->conversation()->create();
    Message::factory()->create(['conversation_id' => $conversation->id, 'author_user_id' => $third->id]);
    expect(recapSummaryFor($this)['conversations'])->toBe(2);
    $third->profile->update(['visibility' => ProfileVisibility::Hidden]);
    expect(recapSummaryFor($this)['conversations'])->toBe(1);
});

test('purging a pending account also deletes reserved weekly deliveries', function () {
    $delivery = reserveRecapFor($this);
    $this->member->forceFill(['status' => UserStatus::PendingDeletion, 'deletion_requested_at' => now()->subDays(31)])->save();
    (new PurgeDeletedUser($this->member->id, $this->member->deletion_requested_at->toISOString()))->handle();
    expect($delivery->fresh())->toBeNull()->and($this->member->fresh())->toBeNull();
});

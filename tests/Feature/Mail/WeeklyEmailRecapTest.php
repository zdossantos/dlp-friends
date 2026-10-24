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
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-11 15:00', 'Europe/Paris'));
    $this->periodEnd = CarbonImmutable::now()->utc();
    $this->member = User::factory()->withProfile()->create(['locale' => 'fr', 'weekly_recap_messages' => true, 'weekly_recap_matches' => true]);
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

test('a relaunch recovers a reservation left pending before queue publication', function () {
    $delivery = reserveRecapFor($this);
    Queue::fake();
    Mail::fake();

    $this->artisan('notifications:dispatch-weekly-recaps')->assertSuccessful();
    Queue::assertPushed(SendWeeklyEmailRecap::class, fn ($job) => $job->deliveryId === $delivery->id);
    expect(WeeklyEmailRecapDelivery::query()->count())->toBe(1);

    (new SendWeeklyEmailRecap($delivery->id))->handle(app(BuildWeeklyEmailRecap::class));
    (new SendWeeklyEmailRecap($delivery->id))->handle(app(BuildWeeklyEmailRecap::class));
    Mail::assertSentCount(1);

    Queue::fake();
    $this->artisan('notifications:dispatch-weekly-recaps')->assertSuccessful();
    Queue::assertNothingPushed();
});

test('relaunches reuse the same reservation and the next week reserves a new delivery', function () {
    Queue::fake();
    $this->artisan('notifications:dispatch-weekly-recaps')->assertSuccessful();
    $this->artisan('notifications:dispatch-weekly-recaps')->assertSuccessful();
    expect(WeeklyEmailRecapDelivery::query()->count())->toBe(1);
    Queue::assertPushed(SendWeeklyEmailRecap::class, 2);
    $this->travelTo($this->periodEnd->setTimezone('Europe/Paris')->addWeek());
    $this->artisan('notifications:dispatch-weekly-recaps')->assertSuccessful();
    expect(WeeklyEmailRecapDelivery::query()->count())->toBe(2);
    Queue::assertPushed(SendWeeklyEmailRecap::class, 3);
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

test('the database rejects duplicate reservations', function () {
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

test('a member without email opt in receives no recap despite old unread messages', function () {
    $this->member->delete();
    $member = User::factory()->withProfile()->create();
    $match = MemberMatch::query()->create([
        'user_low_id' => $this->other->id,
        'user_high_id' => $member->id,
    ]);
    $conversation = $match->conversation()->create();
    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'author_user_id' => $this->other->id,
        'created_at' => $this->periodEnd->subDays(4),
    ]);
    Mail::fake();
    Queue::fake();

    $this->artisan('notifications:dispatch-weekly-recaps')->assertSuccessful();

    expect(WeeklyEmailRecapDelivery::query()->count())->toBe(0);
    Queue::assertNothingPushed();
    Mail::assertNothingSent();
});

test('recap network sending happens outside the delivery transaction', function () {
    $delivery = reserveRecapFor($this);
    $baseline = DB::transactionLevel();
    Mail::shouldReceive('to')->once()->with($this->member->email)->andReturnSelf();
    Mail::shouldReceive('send')->once()->andReturnUsing(function () use ($baseline) {
        expect(DB::transactionLevel())->toBe($baseline);
    });

    (new SendWeeklyEmailRecap($delivery->id))->handle(app(BuildWeeklyEmailRecap::class));
    expect($delivery->fresh()->sent_at)->not->toBeNull();
});

test('resend retries keep the same idempotency key and frozen request after an uncertain acceptance', function () {
    config()->set('mail.default', 'resend');
    config()->set('services.resend.key', 'test-key');
    $delivery = reserveRecapFor($this);
    $requests = [];
    Http::fake(function ($request) use (&$requests) {
        $requests[] = ['key' => $request->header('Idempotency-Key'), 'body' => $request->data()];
        if (count($requests) === 1) {
            throw new ConnectionException('Response lost after acceptance');
        }

        return Http::response(['id' => 'provider-email-id']);
    });
    $job = new SendWeeklyEmailRecap($delivery->id);
    expect(fn () => $job->handle(app(BuildWeeklyEmailRecap::class)))
        ->toThrow(ConnectionException::class);
    $this->member->update(['locale' => 'en']);
    $job->handle(app(BuildWeeklyEmailRecap::class));

    expect($requests)->toHaveCount(2)->and($requests[0]['key'])->not->toBeEmpty()
        ->and($requests[1])->toBe($requests[0])
        ->and($delivery->fresh()->sent_at)->not->toBeNull()
        ->and($delivery->fresh()->resend_payload)->toBeNull();
});

test('a recap waiting on its lease is released and rechecks consent before resuming', function () {
    Mail::fake();
    $delivery = reserveRecapFor($this);
    $delivery->forceFill(['reserved_at' => now()])->save();
    config()->set('queue.connections.database.after_commit', false);
    $queue = Queue::connection('database');
    $queue->push(new SendWeeklyEmailRecap($delivery->id));
    $this->travel(2)->minutes();
    $job = $queue->pop();
    $job->fire();
    expect($job->isReleased())->toBeTrue()->and($queue->size())->toBe(1);
    Mail::assertNothingSent();

    $this->member->forceFill(['weekly_recap_messages' => false])->save();
    $this->travel(9)->minutes();
    $job = $queue->pop();
    $job->fire();
    expect($queue->size())->toBe(0)->and($delivery->fresh()->skipped_at)->not->toBeNull();
    Mail::assertNothingSent();
});

test('an uncertain recap is stopped before the provider idempotency window expires', function () {
    config()->set('mail.default', 'resend');
    config()->set('services.resend.key', 'test-key');
    $attempts = 0;
    Http::fake(function () use (&$attempts) {
        $attempts++;
        throw new ConnectionException('Lost response');
    });
    $delivery = reserveRecapFor($this);
    $job = new SendWeeklyEmailRecap($delivery->id);
    expect(fn () => $job->handle(app(BuildWeeklyEmailRecap::class)))
        ->toThrow(ConnectionException::class);
    expect(DB::table('weekly_email_recap_deliveries')->where('id', $delivery->id)->value('resend_payload'))
        ->not->toContain($this->member->email);
    $this->travel(24)->hours();
    $job->handle(app(BuildWeeklyEmailRecap::class));
    expect($attempts)->toBe(1);
    expect($delivery->fresh()->sent_at)->toBeNull()
        ->and($delivery->fresh()->skipped_at)->not->toBeNull()
        ->and($delivery->fresh()->resend_payload)->toBeNull();
});

test('a pending frozen recap is cancelled after its recipient or match preference changes', function (string $change) {
    config()->set('mail.default', 'resend');
    config()->set('services.resend.key', 'test-key');
    $attempts = 0;
    Http::fake(function () use (&$attempts) {
        $attempts++;
        throw new ConnectionException('Lost response');
    });
    $delivery = reserveRecapFor($this);
    $job = new SendWeeklyEmailRecap($delivery->id);
    expect(fn () => $job->handle(app(BuildWeeklyEmailRecap::class)))
        ->toThrow(ConnectionException::class);
    $this->member->forceFill($change === 'email' ? ['email' => 'changed@example.test'] : ['weekly_recap_matches' => false])->save();
    $job->handle(app(BuildWeeklyEmailRecap::class));

    expect($attempts)->toBe(1);
    expect($delivery->fresh()->skipped_at)->not->toBeNull()
        ->and($delivery->fresh()->resend_payload)->toBeNull();
})->with(['email', 'matches']);

test('exhausted recap attempts discard the temporary encrypted recipient snapshot', function () {
    config()->set('mail.default', 'resend');
    config()->set('services.resend.key', 'test-key');
    $attempts = 0;
    Http::fake(function () use (&$attempts) {
        $attempts++;
        throw new ConnectionException('Lost response');
    });
    $delivery = reserveRecapFor($this);
    $job = new SendWeeklyEmailRecap($delivery->id);
    expect(fn () => $job->handle(app(BuildWeeklyEmailRecap::class)))
        ->toThrow(ConnectionException::class);

    $job->failed(new RuntimeException('Attempts exhausted'));

    expect($delivery->fresh()->skipped_at)->not->toBeNull()
        ->and($delivery->fresh()->resend_payload)->toBeNull()
        ->and($delivery->fresh()->reserved_at)->toBeNull();
});

test('a concurrent recap consumer waits while the first provider request is in flight', function () {
    config()->set('mail.default', 'resend');
    config()->set('services.resend.key', 'test-key');
    $delivery = reserveRecapFor($this);
    $duplicate = (new SendWeeklyEmailRecap($delivery->id))->withFakeQueueInteractions();
    $baseline = DB::transactionLevel();
    Http::fake(function () use ($duplicate, $baseline) {
        expect(DB::transactionLevel())->toBe($baseline);
        $duplicate->handle(app(BuildWeeklyEmailRecap::class));
        $duplicate->assertReleased();
        $duplicate->failed(new RuntimeException('An older duplicate exhausted its attempts'));
        expect(WeeklyEmailRecapDelivery::query()->findOrFail($duplicate->deliveryId)->skipped_at)->toBeNull();

        return Http::response(['id' => 'provider-email-id']);
    });
    (new SendWeeklyEmailRecap($delivery->id))->handle(app(BuildWeeklyEmailRecap::class));

    Http::assertSentCount(1);
    expect($delivery->fresh()->sent_at)->not->toBeNull();
});

test('weekly dispatch clears expired snapshots even when the worker died and consent was withdrawn', function () {
    Queue::fake();
    $delivery = reserveRecapFor($this);
    $delivery->forceFill([
        'reserved_at' => now()->subMinutes(11),
        'first_attempt_at' => now()->subHours(25),
        'idempotency_key' => (string) Str::uuid(),
        'resend_payload' => ['request' => ['to' => [$this->member->email]], 'matches_enabled' => false],
    ])->save();
    $this->member->forceFill(['weekly_recap_messages' => false])->save();

    $this->artisan('notifications:dispatch-weekly-recaps')->assertSuccessful();

    expect($delivery->fresh()->resend_payload)->toBeNull()
        ->and($delivery->fresh()->skipped_at)->not->toBeNull();
    Queue::assertNothingPushed();
});

test('a first recap attempt delayed by a queue outage keeps the documented weekly eligibility window', function () {
    Mail::fake();
    $delivery = reserveRecapFor($this);
    config()->set('queue.connections.database.after_commit', false);
    $queue = Queue::connection('database');
    $queue->push(new SendWeeklyEmailRecap($delivery->id));
    $this->travel(2)->days();

    app('queue.worker')->process('database', $queue->pop(), new WorkerOptions(maxTries: 3));

    Mail::assertSentCount(1);
    expect($delivery->fresh()->sent_at)->not->toBeNull()
        ->and($delivery->fresh()->skipped_at)->toBeNull();
});

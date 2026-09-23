<?php

use App\Actions\DeliverPartnerAnnouncement;
use App\Actions\FinalizePartnerAnnouncement;
use App\Actions\PreparePartnerAnnouncementAudience;
use App\Actions\StartPartnerAnnouncement;
use App\Enums\PartnerAnnouncementStatus;
use App\Enums\PartnerDeliveryStatus;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Jobs\BroadcastPartnerAnnouncement as BroadcastPartnerAnnouncementJob;
use App\Jobs\DeliverPartnerAnnouncement as DeliverPartnerAnnouncementJob;
use App\Jobs\PreparePartnerAnnouncementAudience as PreparePartnerAnnouncementAudienceJob;
use App\Models\PartnerAnnouncement;
use App\Models\PartnerAnnouncementDelivery;
use App\Models\PartnerAnnouncementMetric;
use App\Models\PartnerNotificationPreference;
use App\Models\PartnerProfile;
use App\Models\PartnerSetting;
use App\Models\Role;
use App\Models\User;
use App\Notifications\PartnerAnnouncementNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastingFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function dispatchEligibleUser(array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    PartnerNotificationPreference::query()->create([
        'user_id' => $user->id,
        'enabled' => true,
    ]);

    return $user;
}

function sendingAnnouncement(array $attributes = []): PartnerAnnouncement
{
    $announcement = PartnerAnnouncement::factory()->create([
        'status' => PartnerAnnouncementStatus::Sending,
        'destination_url' => 'https://offers.example.com/member-benefit',
        'run_uuid' => (string) Str::uuid(),
        'sending_started_at' => now(),
        ...$attributes,
    ]);
    PartnerAnnouncementMetric::query()->create([
        'partner_announcement_id' => $announcement->id,
    ]);

    return $announcement;
}

test('an admin starts an approved announcement only after commit and creates its run atomically', function () {
    Queue::fake();
    $admin = User::factory()->admin()->create();
    $announcement = PartnerAnnouncement::factory()->approved()->create([
        'destination_url' => 'https://offers.example.com/start',
    ]);

    DB::beginTransaction();
    app(StartPartnerAnnouncement::class)->handle($admin, $announcement);
    Queue::assertNothingPushed();
    DB::commit();

    Queue::assertPushed(
        PreparePartnerAnnouncementAudienceJob::class,
        fn (PreparePartnerAnnouncementAudienceJob $job): bool => $job->announcementId === $announcement->id,
    );
    $started = $announcement->fresh();
    expect($started?->status)->toBe(PartnerAnnouncementStatus::Sending)
        ->and($started?->run_uuid)->not->toBeNull()
        ->and(Str::isUuid((string) $started?->run_uuid))->toBeTrue()
        ->and($started?->sending_started_at)->not->toBeNull()
        ->and($started?->metric)->not->toBeNull()
        ->and($started?->metric?->prepared_count)->toBe(0)
        ->and($started?->metric?->delivered_count)->toBe(0);
});

test('an admin can resume preparation when the initial after commit queue push failed', function () {
    $queue = Queue::fake();
    $queue->beforePushing(static function (object $job): void {
        if ($job instanceof PreparePartnerAnnouncementAudienceJob) {
            throw new RuntimeException('queue unavailable');
        }
    });
    $admin = User::factory()->admin()->create();
    $recipient = dispatchEligibleUser();
    $announcement = PartnerAnnouncement::factory()->approved()->create([
        'destination_url' => 'https://offers.example.com/resumable-start',
    ]);

    expect(fn () => app(StartPartnerAnnouncement::class)->handle($admin, $announcement))
        ->toThrow(RuntimeException::class, 'queue unavailable');
    expect($announcement->fresh()?->status)->toBe(PartnerAnnouncementStatus::Sending)
        ->and($announcement->fresh()?->audience_prepared_at)->toBeNull();

    Queue::fake();
    $this->actingAs($admin)
        ->post(route('admin.partner-announcements.retry', $announcement))
        ->assertRedirect(route('admin.partner-announcements.index'));
    Queue::assertPushed(PreparePartnerAnnouncementAudienceJob::class, 1);

    $prepare = Queue::pushed(PreparePartnerAnnouncementAudienceJob::class)->firstOrFail();
    Queue::fake();
    $prepare->handle(
        app(PreparePartnerAnnouncementAudience::class),
        app(FinalizePartnerAnnouncement::class),
    );

    expect($announcement->fresh()?->audience_prepared_at)->not->toBeNull()
        ->and($announcement->deliveries()->pluck('user_id')->all())->toBe([$recipient->id]);
    Queue::assertPushed(DeliverPartnerAnnouncementJob::class, 1);
});

test('an admin can resume all pending delivery pushes after preparation was interrupted', function () {
    $pushCount = 0;
    $queue = Queue::fake();
    $queue->beforePushing(static function (object $job) use (&$pushCount): void {
        if (! $job instanceof DeliverPartnerAnnouncementJob) {
            return;
        }

        $pushCount++;

        if ($pushCount === 251) {
            throw new RuntimeException('queue interrupted');
        }
    });
    $admin = User::factory()->admin()->create();
    collect(range(1, 501))->each(fn () => dispatchEligibleUser());
    $announcement = sendingAnnouncement();

    expect(fn () => app(PreparePartnerAnnouncementAudience::class)->handle($announcement))
        ->toThrow(RuntimeException::class, 'queue interrupted');
    expect($announcement->fresh()?->audience_prepared_at)->not->toBeNull()
        ->and($announcement->deliveries()->count())->toBe(501)
        ->and($pushCount)->toBe(251);

    Queue::fake();
    $this->actingAs($admin)
        ->post(route('admin.partner-announcements.retry', $announcement))
        ->assertRedirect(route('admin.partner-announcements.index'));
    Queue::assertPushed(PreparePartnerAnnouncementAudienceJob::class, 1);

    $prepare = Queue::pushed(PreparePartnerAnnouncementAudienceJob::class)->firstOrFail();
    Queue::fake();
    $prepare->handle(
        app(PreparePartnerAnnouncementAudience::class),
        app(FinalizePartnerAnnouncement::class),
    );

    $pendingIds = $announcement->deliveries()
        ->where('status', PartnerDeliveryStatus::Pending)
        ->orderBy('id')
        ->pluck('id')
        ->all();
    $queuedIds = Queue::pushed(DeliverPartnerAnnouncementJob::class)
        ->pluck('deliveryId')
        ->sort()
        ->values()
        ->all();
    expect($queuedIds)->toBe($pendingIds);
});

test('start is admin-only, validates the state and URL, and enforces the decisive cooldown boundary', function () {
    Queue::fake();
    $this->travelTo('2026-09-20 12:00:00');
    $partner = User::factory()->partnerOnly()->create();
    $admin = User::factory()->admin()->create();
    $profile = PartnerProfile::factory()->create();
    PartnerSetting::current()->update(['cooldown_days' => 30]);
    PartnerAnnouncement::factory()->for($profile)->sent()->create([
        'destination_url' => 'https://offers.example.com/previous',
        'sending_started_at' => now()->subDays(30)->addSecond(),
        'sent_at' => now()->subDays(30)->addSecond(),
    ]);
    $approved = PartnerAnnouncement::factory()->for($profile)->approved()->create([
        'destination_url' => 'https://offers.example.com/next',
    ]);

    expect(fn () => app(StartPartnerAnnouncement::class)->handle($partner, $approved))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(StartPartnerAnnouncement::class)->handle($admin, $approved))
        ->toThrow(ValidationException::class);
    expect($approved->fresh()?->status)->toBe(PartnerAnnouncementStatus::Approved);

    $profile->announcements()->whereNotNull('sent_at')->update([
        'sending_started_at' => now()->subDays(30),
        'sent_at' => now()->subDays(30),
    ]);
    app(StartPartnerAnnouncement::class)->handle($admin, $approved);
    expect($approved->fresh()?->status)->toBe(PartnerAnnouncementStatus::Sending);

    $unsafe = PartnerAnnouncement::factory()->approved()->create([
        'destination_url' => 'https://localhost/internal',
    ]);
    expect(fn () => app(StartPartnerAnnouncement::class)->handle($admin, $unsafe))
        ->toThrow(ValidationException::class);
});

test('audience preparation inserts eligible members once and requeues every pending delivery', function () {
    Queue::fake();
    $eligible = collect(range(1, 501))->map(fn (): User => dispatchEligibleUser());
    $withoutConsent = User::factory()->create();
    $unverified = dispatchEligibleUser(['email_verified_at' => null]);
    $pendingDeletion = dispatchEligibleUser([
        'status' => UserStatus::PendingDeletion,
        'deletion_requested_at' => now(),
    ]);
    $partnerOnly = User::factory()->partnerOnly()->create();
    PartnerNotificationPreference::query()->create([
        'user_id' => $partnerOnly->id,
        'enabled' => true,
    ]);
    $announcement = sendingAnnouncement();

    app(PreparePartnerAnnouncementAudience::class)->handle($announcement);
    app(PreparePartnerAnnouncementAudience::class)->handle($announcement->fresh());

    expect($announcement->deliveries()->count())->toBe(501)
        ->and($announcement->deliveries()->pluck('user_id')->all())
        ->toEqualCanonicalizing($eligible->pluck('id')->all())
        ->and($announcement->deliveries()->whereIn('user_id', [
            $withoutConsent->id,
            $unverified->id,
            $pendingDeletion->id,
            $partnerOnly->id,
        ])->exists())->toBeFalse()
        ->and($announcement->fresh()?->audience_prepared_at)->not->toBeNull()
        ->and($announcement->metric?->fresh()?->prepared_count)->toBe(501);
    Queue::assertPushed(DeliverPartnerAnnouncementJob::class, 1002);
});

test('delivery rechecks every eligibility condition and skips members who became ineligible', function (string $condition) {
    config()->set('broadcasting.default', 'null');
    $user = dispatchEligibleUser();
    $announcement = sendingAnnouncement(['audience_prepared_at' => now()]);
    $delivery = PartnerAnnouncementDelivery::factory()->for($announcement, 'announcement')->for($user)->create();

    match ($condition) {
        'consent' => $user->partnerNotificationPreference()->update(['enabled' => false]),
        'activity' => $user->forceFill(['status' => UserStatus::PendingDeletion])->save(),
        'verification' => $user->forceFill(['email_verified_at' => null])->save(),
        'role' => $user->roles()->detach(Role::query()->where('name', RoleName::User)->firstOrFail()),
    };

    app(DeliverPartnerAnnouncement::class)->handle($delivery);

    expect($delivery->fresh()?->status)->toBe(PartnerDeliveryStatus::Skipped)
        ->and($delivery->fresh()?->attempts)->toBe(1)
        ->and($user->notifications()->count())->toBe(0)
        ->and($announcement->metric?->fresh()?->delivered_count)->toBe(0);
})->with(['consent', 'activity', 'verification', 'role']);

test('delivery creates exactly one database notification and increments metrics once', function () {
    config()->set('broadcasting.default', 'null');
    $user = dispatchEligibleUser();
    $announcement = sendingAnnouncement(['audience_prepared_at' => now()]);
    $delivery = PartnerAnnouncementDelivery::factory()->for($announcement, 'announcement')->for($user)->create();

    app(DeliverPartnerAnnouncement::class)->handle($delivery);
    app(DeliverPartnerAnnouncement::class)->handle($delivery->fresh());

    $delivered = $delivery->fresh();
    expect($delivered?->status)->toBe(PartnerDeliveryStatus::Delivered)
        ->and($delivered?->attempts)->toBe(1)
        ->and($delivered?->notification_id)->not->toBeNull()
        ->and($user->notifications()->count())->toBe(1)
        ->and($user->notifications()->firstOrFail()->id)->toBe($delivered?->notification_id)
        ->and($user->notifications()->firstOrFail()->data)->toMatchArray([
            'category' => 'partners',
            'translation_key' => 'notifications.items.partner_announcement',
            'announcement_id' => $announcement->id,
            'target_type' => 'partner_announcement',
            'target_id' => $announcement->id,
        ])
        ->and($announcement->metric?->fresh()?->delivered_count)->toBe(1);
});

test('a rolled back delivery transaction never queues its broadcast', function () {
    Queue::fake();
    expect(config('queue.connections.database.after_commit'))->toBeFalse();
    $user = dispatchEligibleUser();
    $announcement = sendingAnnouncement(['audience_prepared_at' => now()]);
    $delivery = PartnerAnnouncementDelivery::factory()->for($announcement, 'announcement')->for($user)->create();

    $initialTransactionLevel = DB::transactionLevel();
    DB::beginTransaction();
    try {
        app(DeliverPartnerAnnouncement::class)->handle($delivery);
        DB::rollBack();
    } finally {
        while (DB::transactionLevel() > $initialTransactionLevel) {
            DB::rollBack();
        }
    }

    Queue::assertNotPushed(BroadcastEvent::class);
    Queue::assertNotPushed(BroadcastPartnerAnnouncementJob::class);
    expect($delivery->fresh()?->status)->toBe(PartnerDeliveryStatus::Pending)
        ->and($user->notifications()->count())->toBe(0)
        ->and($announcement->metric?->fresh()?->delivered_count)->toBe(0);
});

test('a successful delivery durably queues its broadcast only after commit', function () {
    Queue::fake();
    expect(config('queue.connections.database.after_commit'))->toBeFalse();
    $user = dispatchEligibleUser();
    $announcement = sendingAnnouncement(['audience_prepared_at' => now()]);
    $delivery = PartnerAnnouncementDelivery::factory()->for($announcement, 'announcement')->for($user)->create();

    $initialTransactionLevel = DB::transactionLevel();
    DB::beginTransaction();
    try {
        app(DeliverPartnerAnnouncement::class)->handle($delivery);
        Queue::assertNotPushed(BroadcastEvent::class);
        Queue::assertNotPushed(BroadcastPartnerAnnouncementJob::class);
        $notification = $user->notifications()->firstOrFail();
        DB::commit();
    } finally {
        while (DB::transactionLevel() > $initialTransactionLevel) {
            DB::rollBack();
        }
    }

    Queue::assertPushed(
        BroadcastPartnerAnnouncementJob::class,
        fn (BroadcastPartnerAnnouncementJob $job): bool => $job->deliveryId === $delivery->id,
    );
    Queue::assertNotPushed(BroadcastEvent::class);
    expect($delivery->fresh()?->notification_id)->toBe($notification->id)
        ->and($delivery->fresh()?->broadcasted_at)->toBeNull()
        ->and($announcement->metric?->fresh()?->delivered_count)->toBe(1);
});

test('an admin retry recovers a failed post commit broadcast without duplicating delivery side effects', function () {
    $queue = Queue::fake();
    $queue->beforePushing(static function (object $job): void {
        if ($job instanceof BroadcastPartnerAnnouncementJob) {
            throw new RuntimeException('broadcast queue unavailable');
        }
    });
    $admin = User::factory()->admin()->create();
    $user = dispatchEligibleUser();
    $announcement = sendingAnnouncement(['audience_prepared_at' => now()]);
    $delivery = PartnerAnnouncementDelivery::factory()->for($announcement, 'announcement')->for($user)->create();

    expect(fn () => app(DeliverPartnerAnnouncement::class)->handle($delivery))
        ->toThrow(RuntimeException::class, 'broadcast queue unavailable');

    $delivered = $delivery->fresh();
    $notification = $user->notifications()->firstOrFail();
    expect($delivered?->status)->toBe(PartnerDeliveryStatus::Delivered)
        ->and($delivered?->broadcasted_at)->toBeNull()
        ->and($user->notifications()->count())->toBe(1)
        ->and($announcement->metric?->fresh()?->delivered_count)->toBe(1);

    $announcement->update([
        'status' => PartnerAnnouncementStatus::Sent,
        'sent_at' => now(),
        'title' => 'A title changed after database delivery',
    ]);

    Queue::fake();
    $this->actingAs($admin)
        ->post(route('admin.partner-announcements.retry', $announcement))
        ->assertRedirect(route('admin.partner-announcements.index'));
    Queue::assertPushed(BroadcastPartnerAnnouncementJob::class, 1);
    Queue::assertNotPushed(PreparePartnerAnnouncementAudienceJob::class);

    $broadcast = Queue::pushed(BroadcastPartnerAnnouncementJob::class)->firstOrFail();
    Queue::fake();
    app()->call([$broadcast, 'handle']);

    Queue::assertNotPushed(BroadcastEvent::class);
    expect($delivery->fresh()?->broadcasted_at)->not->toBeNull()
        ->and($user->notifications()->count())->toBe(1)
        ->and($announcement->metric?->fresh()?->delivered_count)->toBe(1);

    app()->call([$broadcast, 'handle']);

    Queue::assertNotPushed(BroadcastEvent::class);
    expect($user->notifications()->count())->toBe(1)
        ->and($announcement->metric?->fresh()?->delivered_count)->toBe(1);
});

test('a failed broadcast transport push remains retryable with the same notification identity', function () {
    Queue::fake();
    $admin = User::factory()->admin()->create();
    $user = dispatchEligibleUser();
    $announcement = sendingAnnouncement(['audience_prepared_at' => now()]);
    $delivery = PartnerAnnouncementDelivery::factory()->for($announcement, 'announcement')->for($user)->create();

    app(DeliverPartnerAnnouncement::class)->handle($delivery);
    $broadcast = Queue::pushed(BroadcastPartnerAnnouncementJob::class)->firstOrFail();
    $notification = $user->notifications()->firstOrFail();

    $broadcaster = Mockery::mock(BroadcastingFactory::class);
    $connection = Mockery::mock(Broadcaster::class);
    $attempts = [];
    $broadcaster->shouldReceive('connection')->times(4)->with(null)->andReturn($connection);
    $connection->shouldReceive('broadcast')->times(4)->andReturnUsing(
        static function (array $channels, string $event, array $payload) use (&$attempts): void {
            $attempts[] = compact('channels', 'event', 'payload');

            if (count($attempts) <= 3) {
                throw new RuntimeException('broadcast transport unavailable');
            }
        },
    );
    app()->instance(BroadcastingFactory::class, $broadcaster);

    foreach (range(1, 3) as $_attempt) {
        expect(fn () => app()->call([$broadcast, 'handle']))
            ->toThrow(RuntimeException::class, 'broadcast transport unavailable');
    }
    expect($delivery->fresh()?->broadcasted_at)->toBeNull()
        ->and($user->notifications()->count())->toBe(1)
        ->and($announcement->metric?->fresh()?->delivered_count)->toBe(1)
        ->and($attempts)->toHaveCount(3);

    foreach ($attempts as $attempt) {
        expect($attempt['channels'])->toHaveCount(1)
            ->and($attempt['channels'][0])->toBeInstanceOf(PrivateChannel::class)
            ->and($attempt['channels'][0]->name)->toBe("private-App.Models.User.{$user->id}")
            ->and($attempt['event'])->toBe(BroadcastNotificationCreated::class)
            ->and($attempt['payload'])->toBe([
                'id' => $notification->id,
                ...$notification->data,
                'type' => PartnerAnnouncementNotification::class,
                'socket' => null,
            ]);
    }

    $announcement->update([
        'status' => PartnerAnnouncementStatus::Sent,
        'sent_at' => now(),
    ]);
    Queue::fake();
    $this->actingAs($admin)
        ->post(route('admin.partner-announcements.retry', $announcement))
        ->assertRedirect(route('admin.partner-announcements.index'));
    $retry = Queue::pushed(BroadcastPartnerAnnouncementJob::class)->firstOrFail();

    Queue::fake();
    app()->call([$retry, 'handle']);

    Queue::assertNotPushed(BroadcastEvent::class);
    expect($delivery->fresh()?->broadcasted_at)->not->toBeNull()
        ->and($user->notifications()->count())->toBe(1)
        ->and($announcement->metric?->fresh()?->delivered_count)->toBe(1)
        ->and($attempts)->toHaveCount(4)
        ->and($attempts[3]['channels'][0]->name)->toBe($attempts[0]['channels'][0]->name)
        ->and($attempts[3]['event'])->toBe($attempts[0]['event'])
        ->and($attempts[3]['payload'])->toBe($attempts[0]['payload']);
});

test('a crash after an accepted broadcast replays the same notification without duplicating durable side effects', function () {
    Queue::fake();
    $admin = User::factory()->admin()->create();
    $user = dispatchEligibleUser();
    $announcement = sendingAnnouncement(['audience_prepared_at' => now()]);
    $delivery = PartnerAnnouncementDelivery::factory()->for($announcement, 'announcement')->for($user)->create();

    app(DeliverPartnerAnnouncement::class)->handle($delivery);
    $broadcast = Queue::pushed(BroadcastPartnerAnnouncementJob::class)->firstOrFail();
    $notification = $user->notifications()->firstOrFail();

    $broadcaster = Mockery::mock(BroadcastingFactory::class);
    $connection = Mockery::mock(Broadcaster::class);
    $attempts = [];
    $broadcaster->shouldReceive('connection')->twice()->with(null)->andReturn($connection);
    $connection->shouldReceive('broadcast')->twice()->andReturnUsing(
        static function (array $channels, string $event, array $payload) use (&$attempts): void {
            $attempts[] = compact('channels', 'event', 'payload');
        },
    );
    app()->instance(BroadcastingFactory::class, $broadcaster);

    $crashBeforeConfirmation = true;
    PartnerAnnouncementDelivery::updating(
        static function (PartnerAnnouncementDelivery $candidate) use (&$crashBeforeConfirmation): void {
            if ($crashBeforeConfirmation && $candidate->isDirty('broadcasted_at')) {
                $crashBeforeConfirmation = false;

                throw new RuntimeException('worker crashed before broadcast confirmation');
            }
        },
    );

    Queue::fake();
    expect(fn () => app()->call([$broadcast, 'handle']))
        ->toThrow(RuntimeException::class, 'worker crashed before broadcast confirmation');
    Queue::assertNotPushed(BroadcastEvent::class);
    expect($delivery->fresh()?->broadcasted_at)->toBeNull()
        ->and($user->notifications()->count())->toBe(1)
        ->and($announcement->metric?->fresh()?->delivered_count)->toBe(1)
        ->and($attempts)->toHaveCount(1)
        ->and($attempts[0]['channels'])->toHaveCount(1)
        ->and($attempts[0]['channels'][0])->toBeInstanceOf(PrivateChannel::class)
        ->and($attempts[0]['channels'][0]->name)->toBe("private-App.Models.User.{$user->id}")
        ->and($attempts[0]['event'])->toBe(BroadcastNotificationCreated::class)
        ->and($attempts[0]['payload'])->toBe([
            'id' => $notification->id,
            ...$notification->data,
            'type' => PartnerAnnouncementNotification::class,
            'socket' => null,
        ]);

    $announcement->update([
        'status' => PartnerAnnouncementStatus::Sent,
        'sent_at' => now(),
    ]);
    Queue::fake();
    $this->actingAs($admin)
        ->post(route('admin.partner-announcements.retry', $announcement))
        ->assertRedirect(route('admin.partner-announcements.index'));
    $retry = Queue::pushed(BroadcastPartnerAnnouncementJob::class)->firstOrFail();

    Queue::fake();
    app()->call([$retry, 'handle']);

    Queue::assertNotPushed(BroadcastEvent::class);
    expect($delivery->fresh()?->broadcasted_at)->not->toBeNull()
        ->and($user->notifications()->count())->toBe(1)
        ->and($announcement->metric?->fresh()?->delivered_count)->toBe(1)
        ->and($attempts)->toHaveCount(2)
        ->and($attempts[1]['channels'][0]->name)->toBe($attempts[0]['channels'][0]->name)
        ->and($attempts[1]['event'])->toBe($attempts[0]['event'])
        ->and($attempts[1]['payload'])->toBe($attempts[0]['payload']);
});

test('terminal delivery failure stores bounded non personal metadata and is retryable by an admin only', function () {
    Queue::fake();
    $admin = User::factory()->admin()->create();
    $partner = User::factory()->partnerOnly()->create();
    $user = dispatchEligibleUser();
    $announcement = sendingAnnouncement(['audience_prepared_at' => now()]);
    $failed = PartnerAnnouncementDelivery::factory()->for($announcement, 'announcement')->for($user)->create([
        'status' => PartnerDeliveryStatus::Pending,
        'attempts' => 3,
    ]);
    $delivered = PartnerAnnouncementDelivery::factory()->for($announcement, 'announcement')->create([
        'status' => PartnerDeliveryStatus::Delivered,
    ]);
    $pending = PartnerAnnouncementDelivery::factory()->for($announcement, 'announcement')->create();
    $job = new DeliverPartnerAnnouncementJob($failed->id);

    $job->failed(new RuntimeException(str_repeat('alice@example.test secret-payload ', 100)));
    expect($failed->fresh()?->status)->toBe(PartnerDeliveryStatus::Failed)
        ->and($failed->fresh()?->last_error)->toContain('RuntimeException')
        ->and($failed->fresh()?->last_error)->not->toContain('alice@example.test')
        ->and($failed->fresh()?->last_error)->not->toContain('secret-payload')
        ->and(strlen((string) $failed->fresh()?->last_error))->toBeLessThanOrEqual(1000);

    $this->actingAs($partner)
        ->post(route('admin.partner-announcements.retry', $announcement))
        ->assertForbidden();
    $this->actingAs($admin)
        ->post(route('admin.partner-announcements.retry', $announcement))
        ->assertRedirect(route('admin.partner-announcements.index'));

    expect($failed->fresh()?->status)->toBe(PartnerDeliveryStatus::Pending)
        ->and($failed->fresh()?->last_error)->toBeNull()
        ->and($failed->fresh()?->attempts)->toBe(3)
        ->and($delivered->fresh()?->status)->toBe(PartnerDeliveryStatus::Delivered)
        ->and($pending->fresh()?->status)->toBe(PartnerDeliveryStatus::Pending);
    Queue::assertPushed(PreparePartnerAnnouncementAudienceJob::class, 1);

    $prepare = Queue::pushed(PreparePartnerAnnouncementAudienceJob::class)->firstOrFail();
    Queue::fake();
    $prepare->handle(
        app(PreparePartnerAnnouncementAudience::class),
        app(FinalizePartnerAnnouncement::class),
    );
    Queue::assertPushed(
        DeliverPartnerAnnouncementJob::class,
        fn (DeliverPartnerAnnouncementJob $queued): bool => $queued->deliveryId === $failed->id,
    );
    Queue::assertPushed(
        DeliverPartnerAnnouncementJob::class,
        fn (DeliverPartnerAnnouncementJob $queued): bool => $queued->deliveryId === $pending->id,
    );
    Queue::assertPushed(DeliverPartnerAnnouncementJob::class, 2);
});

test('finalization waits for prepared audience and all deliveries to become terminal', function () {
    $announcement = sendingAnnouncement();
    $pending = PartnerAnnouncementDelivery::factory()->for($announcement, 'announcement')->create();

    app(FinalizePartnerAnnouncement::class)->handle($announcement);
    expect($announcement->fresh()?->status)->toBe(PartnerAnnouncementStatus::Sending);

    $announcement->update(['audience_prepared_at' => now()]);
    app(FinalizePartnerAnnouncement::class)->handle($announcement->fresh());
    expect($announcement->fresh()?->status)->toBe(PartnerAnnouncementStatus::Sending);

    $pending->update(['status' => PartnerDeliveryStatus::Failed]);
    app(FinalizePartnerAnnouncement::class)->handle($announcement->fresh());
    expect($announcement->fresh()?->status)->toBe(PartnerAnnouncementStatus::Sending);

    $pending->update(['status' => PartnerDeliveryStatus::Skipped]);
    app(FinalizePartnerAnnouncement::class)->handle($announcement->fresh());
    expect($announcement->fresh()?->status)->toBe(PartnerAnnouncementStatus::Sent)
        ->and($announcement->fresh()?->sent_at)->not->toBeNull();
});

test('the dispatch endpoint is restricted to admins', function () {
    Queue::fake();
    $admin = User::factory()->admin()->create();
    $partner = User::factory()->partnerOnly()->create();
    $announcement = PartnerAnnouncement::factory()->approved()->create([
        'destination_url' => 'https://offers.example.com/http-start',
    ]);

    $this->actingAs($partner)
        ->post(route('admin.partner-announcements.dispatch', $announcement))
        ->assertForbidden();
    $this->actingAs($admin)
        ->post(route('admin.partner-announcements.dispatch', $announcement))
        ->assertRedirect(route('admin.partner-statistics.index'));

    expect($announcement->fresh()?->status)->toBe(PartnerAnnouncementStatus::Sending);
});

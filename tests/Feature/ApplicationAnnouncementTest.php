<?php

use App\Actions\BuildUserDataExport;
use App\Actions\PrepareApplicationAnnouncement;
use App\Actions\SendApplicationAnnouncement;
use App\Contracts\WebPushTransport;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Enums\WebPushPreference;
use App\Jobs\DeliverApplicationAnnouncement;
use App\Jobs\DispatchApplicationAnnouncement;
use App\Jobs\SendWebPushNotification;
use App\Models\ApplicationAnnouncement;
use App\Models\Role;
use App\Models\User;
use App\Models\WebPushSubscription;
use App\Notifications\ApplicationAnnouncementNotification;
use App\Support\MemberNotificationPresenter;
use App\Support\WebPushResult;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Contracts\Broadcasting\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function applicationAnnouncementData(array $overrides = []): array
{
    return array_replace([
        'title_fr' => 'Nouvelle version', 'title_en' => 'New version',
        'content_fr' => 'Découvrez les nouveautés.', 'content_en' => 'Discover what is new.',
        'is_release' => true, 'destination_url' => null,
        'audience' => 'all', 'recipient_ids' => [], 'send_push' => false,
    ], $overrides);
}

beforeEach(function () {
    config()->set('inertia.testing.ensure_pages_exist', false);
    Queue::fake();
});

test('only admins can prepare confirm and retry application announcements', function () {
    $member = User::factory()->withProfile()->create();
    $this->actingAs($member)->get('/admin/application-announcements')->assertForbidden();
    $this->post('/admin/application-announcements', applicationAnnouncementData())->assertForbidden();
    $admin = User::factory()->admin()->create();
    $admin->roles()->sync([Role::query()->where('name', RoleName::Admin)->value('id')]);
    $this->actingAs($admin)->get('/admin/application-announcements')->assertOk();
    $this->post('/admin/application-announcements', applicationAnnouncementData())->assertRedirect();
    $announcement = ApplicationAnnouncement::query()->sole();
    expect($announcement->sent_at)->toBeNull();
    $this->actingAs($member)->get("/admin/application-announcements/{$announcement->id}")->assertForbidden();
    $this->post("/admin/application-announcements/{$announcement->id}/send")->assertForbidden();
    $this->post("/admin/application-announcements/{$announcement->id}/retry")->assertForbidden();
});

test('preview snapshots eligible accounts across all roles and excludes opt outs', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create();
    $partner = User::factory()->partnerOnly()->create();
    $unverified = User::factory()->unverified()->create();
    $banned = User::factory()->create(['status' => UserStatus::Banned]);
    $deleting = User::factory()->create(['deletion_requested_at' => now()]);
    $optOut = User::factory()->create();
    $optOut->notificationPreferences()->create(['category' => WebPushPreference::ApplicationAnnouncements, 'enabled' => false]);
    $announcement = app(PrepareApplicationAnnouncement::class)->handle($admin, applicationAnnouncementData());
    expect($announcement->deliveries()->pluck('user_id')->sort()->values()->all())->toBe(collect([$admin->id, $member->id, $partner->id])->sort()->values()->all());
    User::factory()->create();
    $this->actingAs($admin)->get("/admin/application-announcements/{$announcement->id}")
        ->assertInertia(fn (Assert $page) => $page->component('Admin/ApplicationAnnouncements/Show')->where('announcement.counts.pending', 3));
    Queue::assertNothingPushed();
});

test('selected audience is validated server side and contains no unselected account', function () {
    $admin = User::factory()->admin()->create();
    $selected = User::factory()->create();
    User::factory()->create();
    $this->actingAs($admin)->post('/admin/application-announcements', applicationAnnouncementData([
        'audience' => 'selected', 'recipient_ids' => [$selected->id],
    ]))->assertRedirect();
    expect(ApplicationAnnouncement::query()->sole()->deliveries()->pluck('user_id')->all())->toBe([$selected->id]);
    $invalid = User::factory()->unverified()->create();
    $this->post('/admin/application-announcements', applicationAnnouncementData(['audience' => 'selected', 'recipient_ids' => [$invalid->id]]))->assertSessionHasErrors('recipient_ids.0');
    $this->post('/admin/application-announcements', applicationAnnouncementData(['audience' => 'selected']))->assertSessionHasErrors('recipient_ids');
});

test('bilingual content and safe optional urls are validated', function (array $override, string $error) {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->post('/admin/application-announcements', applicationAnnouncementData($override))->assertSessionHasErrors($error);
    expect(ApplicationAnnouncement::query()->count())->toBe(0);
})->with([
    [['title_en' => ''], 'title_en'], [['content_fr' => ''], 'content_fr'],
    [['destination_url' => 'javascript:alert(1)', 'is_release' => false], 'destination_url'],
    [['destination_url' => 'https://user:pass@example.com', 'is_release' => false], 'destination_url'],
    [['destination_url' => 'https://127.0.0.1', 'is_release' => false], 'destination_url'],
    [['audience' => 'everyone'], 'audience'], [['send_push' => 'yes'], 'send_push'],
]);

test('confirmation and retries preserve one persistent notification even after deletion', function () {
    $admin = User::factory()->admin()->create();
    $recipient = User::factory()->withProfile()->create(['locale' => 'en']);
    $announcement = app(PrepareApplicationAnnouncement::class)->handle($admin, applicationAnnouncementData(['audience' => 'selected', 'recipient_ids' => [$recipient->id]]));
    $this->actingAs($admin)->post("/admin/application-announcements/{$announcement->id}/send")->assertRedirect();
    $this->post("/admin/application-announcements/{$announcement->id}/send")->assertRedirect();
    Queue::assertPushed(DispatchApplicationAnnouncement::class);
    $delivery = $announcement->deliveries()->sole();
    app()->call([new DeliverApplicationAnnouncement($delivery->id), 'handle']);
    app()->call([new DeliverApplicationAnnouncement($delivery->id), 'handle']);
    expect($recipient->notifications()->count())->toBe(1);
    $notification = $recipient->notifications()->sole();
    expect($notification->data['content'])->toBe('Discover what is new.');
    $presented = app(MemberNotificationPresenter::class)->present($notification, $recipient);
    expect($presented['category'])->toBe('application')->and($presented['link_url'])->toBe('/en/whats-new');
    $this->actingAs($recipient)->get('/notifications?category=application')->assertOk();
    $this->patch("/notifications/{$notification->id}/mark-read")->assertRedirect();
    expect($notification->fresh()->read_at)->not->toBeNull();
    $this->delete("/notifications/{$notification->id}")->assertRedirect();
    app()->call([new DeliverApplicationAnnouncement($delivery->id), 'handle']);
    expect($recipient->notifications()->count())->toBe(0)->and($delivery->fresh()->status)->toBe('delivered');
});

test('delivery rechecks account eligibility and announcement preference', function (string $change) {
    $admin = User::factory()->admin()->create();
    $recipient = User::factory()->create();
    $announcement = app(PrepareApplicationAnnouncement::class)->handle($admin, applicationAnnouncementData(['audience' => 'selected', 'recipient_ids' => [$recipient->id]]));
    app(SendApplicationAnnouncement::class)->handle($announcement);
    match ($change) {
        'unverified' => $recipient->forceFill(['email_verified_at' => null])->save(),
        'banned' => $recipient->forceFill(['status' => UserStatus::Banned])->save(),
        'deleting' => $recipient->forceFill(['deletion_requested_at' => now()])->save(),
        'preference' => $recipient->notificationPreferences()->create(['category' => WebPushPreference::ApplicationAnnouncements, 'enabled' => false]),
    };
    $delivery = $announcement->deliveries()->sole();
    app()->call([new DeliverApplicationAnnouncement($delivery->id), 'handle']);
    expect($recipient->notifications()->count())->toBe(0)->and($delivery->fresh()->status)->toBe('skipped');
})->with(['unverified', 'banned', 'deleting', 'preference']);

test('drafts cannot deliver and failed deliveries can be resumed without changing content', function () {
    $admin = User::factory()->admin()->create();
    $announcement = app(PrepareApplicationAnnouncement::class)->handle($admin, applicationAnnouncementData());
    $delivery = $announcement->deliveries()->first();
    app()->call([new DeliverApplicationAnnouncement($delivery->id), 'handle']);
    expect($delivery->fresh()->status)->toBe('pending')->and($admin->notifications()->count())->toBe(0);
    app(SendApplicationAnnouncement::class)->handle($announcement);
    $delivery->update(['status' => 'failed']);
    $this->actingAs($admin)->post("/admin/application-announcements/{$announcement->id}/retry")->assertRedirect();
    app()->call([new DispatchApplicationAnnouncement($announcement->id), 'handle']);
    Queue::assertPushed(DeliverApplicationAnnouncement::class, fn ($job) => $job->deliveryId === $delivery->id);
    app()->call([new DeliverApplicationAnnouncement($delivery->id), 'handle']);
    expect($delivery->fresh()->status)->toBe('delivered');
});

test('push is optional and uses existing subscription and preference checks', function (bool $push, bool $subscribed, bool $enabled, int $expected) {
    config()->set(['services.web_push.subject' => 'mailto:test@example.com', 'services.web_push.public_key' => 'test', 'services.web_push.private_key' => 'test']);
    $admin = User::factory()->admin()->create();
    $recipient = User::factory()->create();
    if ($subscribed) {
        WebPushSubscription::factory()->for($recipient)->create();
    }
    $announcement = app(PrepareApplicationAnnouncement::class)->handle($admin, applicationAnnouncementData(['audience' => 'selected', 'recipient_ids' => [$recipient->id], 'send_push' => $push]));
    app(SendApplicationAnnouncement::class)->handle($announcement);
    $recipient->notificationPreferences()->create(['category' => WebPushPreference::ApplicationAnnouncements, 'enabled' => $enabled]);
    $delivery = $announcement->deliveries()->sole();
    app()->call([new DeliverApplicationAnnouncement($delivery->id), 'handle']);
    Queue::assertPushed(SendWebPushNotification::class, $expected);
})->with([[false, true, true, 0], [true, false, true, 0], [true, true, false, 0], [true, true, true, 1]]);

test('preferences can be changed independently and disable all includes application announcements', function () {
    $user = User::factory()->withProfile()->create();
    $this->actingAs($user)->patch('/settings/notifications', ['application_announcements' => false])->assertSessionHasNoErrors();
    expect(User::query()->eligibleForApplicationAnnouncements()->whereKey($user->id)->exists())->toBeFalse();
    $this->patch('/settings/notifications', ['application_announcements' => true])->assertSessionHasNoErrors();
    expect(User::query()->eligibleForApplicationAnnouncements()->whereKey($user->id)->exists())->toBeTrue();
    $this->delete('/settings/notifications')->assertRedirect();
    expect(User::query()->eligibleForApplicationAnnouncements()->whereKey($user->id)->exists())->toBeFalse();
});

test('recipients cannot read delete or inspect another recipients notification or audience', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->withProfile()->create();
    $other = User::factory()->withProfile()->create();
    $announcement = app(PrepareApplicationAnnouncement::class)->handle($admin, applicationAnnouncementData());
    app(SendApplicationAnnouncement::class)->handle($announcement);
    $delivery = $announcement->deliveries()->where('user_id', $owner->id)->sole();
    app()->call([new DeliverApplicationAnnouncement($delivery->id), 'handle']);
    $id = $owner->notifications()->sole()->id;
    $this->actingAs($other)->patch("/notifications/{$id}/mark-read")->assertNotFound();
    $this->delete("/notifications/{$id}")->assertNotFound();
    $this->get("/admin/application-announcements/{$announcement->id}")->assertForbidden();
    expect($owner->notifications()->sole()->data)->not->toHaveKeys(['recipient_ids', 'user_ids']);
});

test('optional links and content are preserved while confirmation ignores replacement input', function (?string $link) {
    $admin = User::factory()->admin()->create(['locale' => 'fr']);
    $this->actingAs($admin)->post('/admin/application-announcements', applicationAnnouncementData([
        'is_release' => false, 'destination_url' => $link,
        'content_fr' => '<script>alert(1)</script>',
    ]))->assertRedirect();
    $announcement = ApplicationAnnouncement::query()->sole();
    $this->post("/admin/application-announcements/{$announcement->id}/send", ['content_fr' => 'Replaced', 'send_push' => true])->assertRedirect();
    $delivery = $announcement->deliveries()->sole();
    app()->call([new DeliverApplicationAnnouncement($delivery->id), 'handle']);
    expect($admin->notifications()->sole()->data)->toMatchArray(['content' => '<script>alert(1)</script>', 'link_url' => $link]);
    expect($announcement->fresh()->send_push)->toBeFalse();
})->with([null, 'https://example.com/updates']);

test('all role-only recipients can consult their announcements and receive a usable push target', function (string $role, string $url) {
    $admin = User::factory()->admin()->create();
    $recipient = User::factory()->create();
    $recipient->roles()->sync([Role::query()->where('name', $role)->value('id')]);
    $announcement = app(PrepareApplicationAnnouncement::class)->handle($admin, applicationAnnouncementData(['audience' => 'selected', 'recipient_ids' => [$recipient->id]]));
    app(SendApplicationAnnouncement::class)->handle($announcement);
    app()->call([new DeliverApplicationAnnouncement($announcement->deliveries()->sole()->id), 'handle']);
    $this->actingAs($recipient)->get($url.'?category=application')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('notifications.data', 1)->where('notifications.data.0.category', 'application'));
    expect((new ApplicationAnnouncementNotification($announcement))->webPushTarget($recipient)->url)->toBe($url);
})->with([['admin', '/admin/notifications'], ['partner', '/partner/notifications']]);

test('failed database delivery can resume and broadcast is sent only once', function () {
    $admin = User::factory()->admin()->create();
    $announcement = app(PrepareApplicationAnnouncement::class)->handle($admin, applicationAnnouncementData());
    app(SendApplicationAnnouncement::class)->handle($announcement);
    $delivery = $announcement->deliveries()->sole();
    $job = new DeliverApplicationAnnouncement($delivery->id);
    $database = Mockery::mock(DatabaseChannel::class);
    $database->shouldReceive('send')->once()->andThrow(new RuntimeException('Database unavailable'));
    expect(fn () => app()->call([$job, 'handle'], ['database' => $database]))->toThrow(RuntimeException::class);
    $job->failed(new RuntimeException('Database unavailable'));
    expect($delivery->fresh()->status)->toBe('failed')->and($admin->notifications()->count())->toBe(0);
    $factory = Mockery::mock(Factory::class);
    $connection = Mockery::mock(Broadcaster::class);
    $factory->shouldReceive('connection')->once()->with(null)->andReturn($connection);
    $connection->shouldReceive('broadcast')->once();
    app()->instance(Factory::class, $factory);
    app()->call([$job, 'handle']);
    app()->call([$job, 'handle']);
    expect($delivery->fresh()->status)->toBe('delivered')->and($admin->notifications()->count())->toBe(1);
});

test('queued push rechecks eligibility before transport and deduplicates deliveries', function (string $change) {
    config()->set('services.web_push', ['subject' => 'mailto:test@example.com', 'public_key' => 'test', 'private_key' => 'test']);
    $admin = User::factory()->admin()->create();
    $recipient = User::factory()->create();
    WebPushSubscription::factory()->for($recipient)->create();
    $announcement = app(PrepareApplicationAnnouncement::class)->handle($admin, applicationAnnouncementData(['audience' => 'selected', 'recipient_ids' => [$recipient->id], 'send_push' => true]));
    app(SendApplicationAnnouncement::class)->handle($announcement);
    app()->call([new DeliverApplicationAnnouncement($announcement->deliveries()->sole()->id), 'handle']);
    $job = Queue::pushed(SendWebPushNotification::class)->sole();
    match ($change) {
        'preference' => $recipient->notificationPreferences()->create(['category' => WebPushPreference::ApplicationAnnouncements, 'enabled' => false]),
        'deletion' => $recipient->forceFill(['deletion_requested_at' => now()])->save(),
        'notification' => $recipient->notifications()->delete(),
        default => null,
    };
    $transport = Mockery::mock(WebPushTransport::class);
    if ($change === 'none') {
        $transport->shouldReceive('send')->once()->andReturn(new WebPushResult(true));
    } else {
        $transport->shouldNotReceive('send');
    }
    app()->instance(WebPushTransport::class, $transport);
    app()->call([$job, 'handle']);
    app()->call([$job, 'handle']);
})->with(['preference', 'deletion', 'notification', 'none']);

test('own data export and account deletion preserve recipient privacy', function () {
    $admin = User::factory()->admin()->create();
    $recipient = User::factory()->withProfile()->create(['locale' => 'fr']);
    $other = User::factory()->create();
    $announcement = app(PrepareApplicationAnnouncement::class)->handle($admin, applicationAnnouncementData());
    app(SendApplicationAnnouncement::class)->handle($announcement);
    app()->call([new DeliverApplicationAnnouncement($announcement->deliveries()->where('user_id', $recipient->id)->sole()->id), 'handle']);
    $export = app(BuildUserDataExport::class)->handle($recipient);
    expect($export['notifications'])->toHaveCount(1)
        ->and($export['notifications'][0]['content'])->toBe('Découvrez les nouveautés.')
        ->and($export['notifications'][0]['link_url'])->toBe('/fr/nouveautes')
        ->and($export['notification_preferences']['application_announcements'])->toBeTrue()
        ->and(json_encode($export))->not->toContain($other->email);
    $recipient->notifications()->delete();
    $afterDeletion = app(BuildUserDataExport::class)->handle($recipient);
    expect($afterDeletion['notifications'])->toBe([])
        ->and($afterDeletion['application_announcement_deliveries'])->toHaveCount(1)
        ->and($afterDeletion['application_announcement_deliveries'][0]['announcement_id'])->toBe($announcement->id)
        ->and($afterDeletion['application_announcement_deliveries'][0]['status'])->toBe('delivered')
        ->and(json_encode($afterDeletion))->not->toContain($other->email);
    $recipient->delete();
    expect($announcement->deliveries()->where('user_id', $recipient->id)->exists())->toBeFalse();
    $admin->delete();
    expect($announcement->fresh()->author_user_id)->toBeNull();
});

<?php

use App\Actions\BuildUserDataExport;
use App\Actions\CreateSocialUser;
use App\Actions\DeliverWebPushNotification;
use App\Actions\Fortify\CreateNewUser;
use App\Actions\NotifyAdminsOfNewMember;
use App\Actions\NotifyAdminsOfPartnerModerationRequest;
use App\Contracts\WebPushTransport;
use App\Data\PendingSocialIdentity;
use App\Enums\RoleName;
use App\Enums\SocialProvider;
use App\Enums\UserStatus;
use App\Enums\WebPushPreference;
use App\Jobs\SendWebPushNotification;
use App\Models\Avatar;
use App\Models\Role;
use App\Models\User;
use App\Models\WebPushSubscription;
use App\Notifications\NewMemberNotification;
use App\Support\MemberNotificationPresenter;
use App\Support\WebPushResult;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastingFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function registerArrivalMember(string $provider = 'email'): User
{
    if ($provider === 'google') {
        return app(CreateSocialUser::class)->execute(
            new PendingSocialIdentity(SocialProvider::Google, 'new-google-member', 'arrival@example.test'),
            '2000-01-01',
        );
    }

    return app(CreateNewUser::class)->create([
        'email' => 'arrival@example.test', 'birth_date' => '2000-01-01',
        'password' => 'password', 'password_confirmation' => 'password', 'terms_accepted' => '1',
    ]);
}

function arrivalProfilePayload(): array
{
    return [
        'display_name' => 'Nouveau membre', 'avatar_id' => Avatar::factory()->create()->id,
        'bio' => null, 'visit_frequency' => 'often', 'visibility' => 'visible', 'interest_ids' => [],
    ];
}

test('self registrations notify admins only after the verified member completes their profile', function (string $provider) {
    Queue::fake();
    $admin = User::factory()->admin()->create();
    $member = registerArrivalMember($provider);
    expect($admin->notifications()->count())->toBe(0);

    $member->forceFill(['email_verified_at' => now()])->save();
    $member->refresh();
    expect($admin->notifications()->count())->toBe(0);

    $payload = arrivalProfilePayload();
    $this->actingAs($member)->post(route('member-profile.store'), $payload)
        ->assertRedirect(route('onboarding.show'));

    expect($admin->notifications()->count())->toBe(1);
    $notification = $admin->notifications()->firstOrFail();
    expect($notification->data)->toBe([
        'category' => 'administration',
        'translation_key' => 'notifications.items.new_member',
        'parameters' => [],
        'target_type' => 'admin_member',
        'target_id' => $member->id,
    ]);

    $this->actingAs($member)->post(route('member-profile.store'), $payload)->assertRedirect('/app');
    expect($admin->notifications()->count())->toBe(1);
})->with(['email', 'google']);

test('admins have an individual new member alert preference enabled by default', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get(route('notification-preferences.edit'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('adminNewMemberAlertsEnabled', true));

    $this->patch(route('notification-preferences.update'), ['admin_new_member_alerts' => false])
        ->assertRedirect(route('notification-preferences.edit'));
    $this->actingAs($admin->refresh())->get(route('notification-preferences.edit'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('adminNewMemberAlertsEnabled', false));
});

test('new member notifications open the exact administrative member card', function () {
    Queue::fake();
    $admin = User::factory()->admin()->create();
    $member = registerArrivalMember();
    $member->forceFill(['email_verified_at' => now()])->save();
    $this->actingAs($member->refresh())->post(route('member-profile.store'), arrivalProfilePayload());
    $notification = $admin->notifications()->firstOrFail();
    $url = '/admin/members?member='.$member->id;

    $this->actingAs($admin)->patch(route('notifications.read', $notification))
        ->assertRedirect($url);
    $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('members.data', 1)->where('members.data.0.id', $member->id));

    $member->forceFill(['status' => UserStatus::PendingDeletion, 'deletion_requested_at' => now()])->save();
    expect(app(MemberNotificationPresenter::class)->targetUrl($notification->data, $admin))
        ->toBe('/notifications');
    $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->has('members.data', 0));
    $member->delete();
    $this->patch(route('notifications.read', $notification))->assertRedirect('/notifications');
});

test('only active admins who enable arrival alerts receive them without altering partner submissions', function () {
    Queue::fake();
    $enabled = User::factory()->admin()->create();
    $disabled = User::factory()->admin()->create();
    $this->actingAs($disabled)->patch(route('notification-preferences.update'), ['admin_new_member_alerts' => false]);
    $inactive = User::factory()->admin()->create(['status' => UserStatus::PendingDeletion]);
    $deleting = User::factory()->admin()->create(['deletion_requested_at' => now()]);
    $ordinary = User::factory()->create();
    $formerAdmin = User::factory()->admin()->create();
    $formerAdmin->roles()->detach($formerAdmin->roles->firstWhere('name', RoleName::Admin));
    $member = registerArrivalMember('google');

    $this->actingAs($member)->post(route('member-profile.store'), arrivalProfilePayload());
    expect($enabled->notifications()->count())->toBe(1);
    foreach ([$disabled, $inactive, $deleting, $ordinary, $formerAdmin] as $excluded) {
        expect($excluded->notifications()->count())->toBe(0);
    }

    app(NotifyAdminsOfPartnerModerationRequest::class)->handle(
        'notifications.items.partner_profile_review_requested', ['partner' => 'Partenaire'],
        'admin_partner_profile_review', 1,
    );
    expect($disabled->notifications()->count())->toBe(1);

    $this->actingAs($enabled)->patch(route('notification-preferences.update'), ['admin_new_member_alerts' => false]);
    expect($enabled->notifications()->count())->toBe(2);
});

test('an arrival is never replayed after notification deletion preference changes or profile recovery', function () {
    Queue::fake();
    $admin = User::factory()->admin()->create();
    $member = registerArrivalMember('google');
    $payload = arrivalProfilePayload();
    $this->actingAs($member)->post(route('member-profile.store'), $payload);
    $admin->notifications()->delete();
    $member->profile()->update(['onboarding_completed_at' => null]);
    $this->actingAs($member->refresh())->post(route('member-profile.store'), $payload);
    app(NotifyAdminsOfNewMember::class)->handle($member);
    expect($admin->notifications()->count())->toBe(0);
});

test('arrivals excluded by preferences are not delivered retroactively', function () {
    Queue::fake();
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->patch(route('notification-preferences.update'), ['admin_new_member_alerts' => false]);
    $member = registerArrivalMember('google');
    $this->actingAs($member)->post(route('member-profile.store'), arrivalProfilePayload());
    $this->actingAs($admin)->patch(route('notification-preferences.update'), ['admin_new_member_alerts' => true]);
    app(NotifyAdminsOfNewMember::class)->handle($member);
    expect($admin->notifications()->count())->toBe(0);
});

test('unverified registrations and accounts created outside self registration do not trigger arrival alerts', function () {
    Queue::fake();
    $admin = User::factory()->admin()->create();
    $unverified = registerArrivalMember();
    $this->actingAs($unverified->refresh())->post(route('member-profile.store'), arrivalProfilePayload())
        ->assertRedirect(route('verification.notice'));
    $manual = User::factory()->create();
    $this->actingAs($manual)->post(route('member-profile.store'), arrivalProfilePayload());
    $manual->roles()->sync([]);
    $manual->roles()->attach(Role::query()->where('name', RoleName::User)->firstOrFail());
    app(NotifyAdminsOfNewMember::class)->handle($manual);
    expect($admin->notifications()->count())->toBe(0);
});

test('non admins cannot change the arrival setting and role removal revokes the notification target', function () {
    Queue::fake();
    $admin = User::factory()->admin()->create();
    $member = registerArrivalMember('google');
    $this->actingAs($member)->post(route('member-profile.store'), arrivalProfilePayload());
    $notification = $admin->notifications()->firstOrFail();
    $this->actingAs($member)->patch(route('notification-preferences.update'), ['admin_new_member_alerts' => false])
        ->assertForbidden();
    $this->get(route('notification-preferences.edit'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('adminNewMemberAlertsEnabled', null));
    $this->patch(route('notifications.read', $notification))->assertNotFound();

    $admin->roles()->detach($admin->roles->firstWhere('name', RoleName::Admin));
    $this->actingAs($admin->refresh())->get('/admin/members?member='.$member->id)->assertForbidden();
    $this->patch(route('notification-preferences.update'), ['admin_new_member_alerts' => false])->assertForbidden();
    expect(app(MemberNotificationPresenter::class)->targetUrl($notification->data, $admin))->toBe('/notifications');
});

test('arrival preferences are exported independently of the administration push preference', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->patch(route('notification-preferences.update'), ['admin_new_member_alerts' => false]);
    $export = app(BuildUserDataExport::class)->handle($admin->refresh());
    expect($export['admin_new_member_alerts_enabled'])->toBeFalse()
        ->and($export['notification_preferences']['administration'])->toBeTrue();
});

test('arrival diffusion waits for commit and queues only active permitted admin devices', function () {
    Queue::fake();
    config()->set('services.web_push', [
        'subject' => 'mailto:test@example.test', 'public_key' => 'public', 'private_key' => 'private',
    ]);
    $admin = User::factory()->admin()->create();
    $muted = User::factory()->admin()->create();
    $muted->notificationPreferences()->create(['category' => WebPushPreference::Administration, 'enabled' => false]);
    $activeDevice = WebPushSubscription::factory()->for($admin)->create();
    WebPushSubscription::factory()->for($admin)->create(['revoked_at' => now()]);
    WebPushSubscription::factory()->for($muted)->create();
    $member = registerArrivalMember('google');

    DB::transaction(function () use ($member): void {
        $this->actingAs($member)->post(route('member-profile.store'), arrivalProfilePayload());
        Queue::assertNothingPushed();
    });
    expect($admin->notifications()->count())->toBe(1)->and($muted->notifications()->count())->toBe(1);
    Queue::assertPushed(SendWebPushNotification::class, 1);
    Queue::assertPushed(SendWebPushNotification::class, fn ($job) => $job->subscriptionId === $activeDevice->id);
    Queue::assertPushed(BroadcastEvent::class, 2);
    app(NotifyAdminsOfNewMember::class)->handle($member);
    Queue::assertPushed(SendWebPushNotification::class, 1);
    Queue::assertPushed(BroadcastEvent::class, 2);
});

test('a failed profile transaction leaves no arrival notification or diffusion and can be retried', function () {
    Queue::fake();
    $admin = User::factory()->admin()->create();
    $member = registerArrivalMember('google');
    $payload = arrivalProfilePayload();
    try {
        DB::transaction(function () use ($member, $payload): void {
            $this->actingAs($member)->post(route('member-profile.store'), $payload);
            throw new RuntimeException('Rollback profile completion');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Rollback profile completion');
    }
    expect($admin->notifications()->count())->toBe(0)
        ->and($member->fresh()->new_member_announced_at)->toBeNull();
    Queue::assertNothingPushed();
    $this->actingAs($member->refresh())->post(route('member-profile.store'), $payload)->assertRedirect(route('onboarding.show'));
    expect($admin->notifications()->count())->toBe(1);
});

test('queued arrival broadcasts recheck the preference and administrator role before delivery', function () {
    Queue::fake();
    $admin = User::factory()->admin()->create();
    $member = registerArrivalMember('google');
    $this->actingAs($member)->post(route('member-profile.store'), arrivalProfilePayload());
    $job = Queue::pushed(BroadcastEvent::class)->first();
    $broadcasting = Mockery::mock(BroadcastingFactory::class);
    $broadcasting->shouldNotReceive('connection');
    $admin->forceFill(['admin_new_member_alerts' => false])->save();
    $job->handle($broadcasting);
    $admin->forceFill(['admin_new_member_alerts' => true])->save();
    $admin->roles()->detach($admin->roles->firstWhere('name', RoleName::Admin));
    $job->handle($broadcasting);
    expect($admin->notifications()->count())->toBe(1);
});

test('a broadcast dispatch failure preserves profile completion and continues other alert channels', function () {
    Queue::fake();
    config()->set('services.web_push', [
        'subject' => 'mailto:test@example.test', 'public_key' => 'public', 'private_key' => 'private',
    ]);
    $admins = User::factory()->admin()->count(2)->create();
    foreach ($admins as $admin) {
        WebPushSubscription::factory()->for($admin)->create();
    }
    $attempts = 0;
    Event::listen('*', function (string $name, array $data) use (&$attempts): void {
        if (($data[0] ?? null) instanceof BroadcastNotificationCreated && ++$attempts === 1) {
            throw new RuntimeException('Queue unavailable');
        }
    });
    $member = registerArrivalMember('google');
    $this->actingAs($member)->post(route('member-profile.store'), arrivalProfilePayload())
        ->assertRedirect(route('onboarding.show'));
    expect($member->fresh()->profile->isComplete())->toBeTrue()
        ->and($member->fresh()->new_member_announced_at)->not->toBeNull()
        ->and($attempts)->toBe(2);
    foreach ($admins as $admin) {
        expect($admin->notifications()->count())->toBe(1);
    }
    Queue::assertPushed(SendWebPushNotification::class, 2);
});

test('new member push is generic localized idempotent and revocable before delivery', function (string $locale, string $title, string $body) {
    Queue::fake();
    $admin = User::factory()->admin()->create(['locale' => $locale]);
    $member = registerArrivalMember('google');
    $this->actingAs($member)->post(route('member-profile.store'), arrivalProfilePayload());
    $stored = $admin->notifications()->firstOrFail();
    $member->profile()->update(['display_name' => 'Profil confidentiel']);
    $notification = new NewMemberNotification($member->id);
    $notification->id = $stored->id;
    $device = WebPushSubscription::factory()->for($admin)->create();
    $transport = Mockery::mock(WebPushTransport::class);
    $transport->shouldReceive('send')->once()->withArgs(function ($subscription, array $payload) use ($title, $body, $locale, $member): bool {
        expect($payload['title'])->toBe($title)->and($payload['body'])->toBe($body)
            ->and($payload['locale'])->toBe($locale)
            ->and($payload['target'])->toBe('/admin/members?member='.$member->id)
            ->and(json_encode($payload))->not->toContain($member->email)
            ->and(json_encode($payload))->not->toContain('Profil confidentiel');

        return true;
    })->andReturn(new WebPushResult(true, false, 201));
    $deliver = new DeliverWebPushNotification($transport);
    $deliver->handle($device, $notification);
    $deliver->handle($device->refresh(), $notification);
    $admin->forceFill(['admin_new_member_alerts' => false])->save();
    $deliver->handle(WebPushSubscription::factory()->for($admin)->create(), $notification);
    $admin->forceFill(['admin_new_member_alerts' => true])->save();
    $admin->roles()->detach($admin->roles->firstWhere('name', RoleName::Admin));
    $deliver->handle(WebPushSubscription::factory()->for($admin)->create(), $notification);
})->with([
    ['fr', 'Nouveau membre', 'Un nouveau membre a rejoint DLP Friends.'],
    ['en', 'New member', 'A new member has joined DLP Friends.'],
]);

test('disable all also disables future arrival alerts while preserving stored notifications', function () {
    Queue::fake();
    $admin = User::factory()->admin()->create();
    $member = registerArrivalMember('google');
    $this->actingAs($member)->post(route('member-profile.store'), arrivalProfilePayload());
    $this->actingAs($admin)->delete(route('notification-preferences.disable-all'))
        ->assertRedirect(route('notification-preferences.edit'));
    expect($admin->fresh()->admin_new_member_alerts)->toBeFalse()
        ->and($admin->notifications()->count())->toBe(1);
});

<?php

use App\Models\User;
use App\Models\WebPushSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('member can idempotently register and revoke only their device', function () {
    $user = User::factory()->withProfile()->create();
    $payload = [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/device',
        'keys' => ['p256dh' => str_repeat('a', 88), 'auth' => str_repeat('b', 22)],
        'device_name' => 'Téléphone', 'platform' => 'android',
    ];

    $first = $this->actingAs($user)->postJson('/settings/notifications/devices', $payload)->assertCreated();
    $this->actingAs($user)->postJson('/settings/notifications/devices', $payload)->assertOk();

    expect(WebPushSubscription::query()->count())->toBe(1)
        ->and($first->json())->not->toHaveKeys(['endpoint', 'keys', 'p256dh', 'auth']);

    $this->actingAs($user)->deleteJson('/settings/notifications/devices/'.$first->json('uuid'))->assertNoContent();
    expect(WebPushSubscription::query()->whereNotNull('revoked_at')->count())->toBe(1);
});

test('an endpoint cannot be transferred to another member', function () {
    $owner = User::factory()->withProfile()->create();
    $other = User::factory()->withProfile()->create();
    $payload = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/shared', 'keys' => ['p256dh' => str_repeat('a', 88), 'auth' => str_repeat('b', 22)]];

    $this->actingAs($owner)->postJson('/settings/notifications/devices', $payload)->assertCreated();
    $this->actingAs($other)->postJson('/settings/notifications/devices', $payload)->assertUnprocessable();
});

test('a partner-only account can register a notification device', function () {
    $partner = User::factory()->partnerOnly()->create();

    $this->actingAs($partner)->postJson('/settings/notifications/devices', [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/partner-device',
        'keys' => ['p256dh' => str_repeat('a', 88), 'auth' => str_repeat('b', 22)],
    ])->assertCreated();

    expect($partner->webPushSubscriptions()->count())->toBe(1);
});

test('push endpoints cannot target arbitrary or private servers', function (string $endpoint) {
    $user = User::factory()->withProfile()->create();

    $this->actingAs($user)->postJson('/settings/notifications/devices', [
        'endpoint' => $endpoint,
        'keys' => ['p256dh' => str_repeat('a', 88), 'auth' => str_repeat('b', 22)],
    ])->assertUnprocessable()->assertJsonValidationErrors('endpoint');
})->with([
    'loopback IPv4' => 'https://127.0.0.1/push',
    'loopback IPv6' => 'https://[::1]/push',
    'private DNS name' => 'https://push.internal.example/push',
    'lookalike host' => 'https://fcm.googleapis.com.attacker.example/push',
]);

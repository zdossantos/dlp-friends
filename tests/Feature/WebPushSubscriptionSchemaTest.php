<?php

use App\Enums\WebPushPreference;
use App\Models\User;
use App\Models\WebPushSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('web push secrets are encrypted and hidden', function () {
    $user = User::factory()->create();
    $subscription = WebPushSubscription::factory()->for($user)->create([
        'endpoint' => 'https://push.example.test/secret-endpoint',
        'p256dh' => 'public-client-key',
        'auth' => 'private-client-key',
    ]);

    $raw = DB::table('web_push_subscriptions')->find($subscription->id);

    expect($raw->endpoint)->not->toContain('secret-endpoint')
        ->and($raw->p256dh)->not->toBe('public-client-key')
        ->and($raw->auth)->not->toBe('private-client-key')
        ->and($subscription->toArray())->not->toHaveKeys(['endpoint', 'p256dh', 'auth'])
        ->and(WebPushPreference::cases())->toHaveCount(5);
});

test('subscriptions and preferences cascade with their owner', function () {
    $user = User::factory()->create();
    WebPushSubscription::factory()->for($user)->create();
    $user->notificationPreferences()->create([
        'category' => WebPushPreference::Messages,
        'enabled' => true,
    ]);

    $user->delete();

    expect(WebPushSubscription::query()->count())->toBe(0)
        ->and(DB::table('notification_preferences')->count())->toBe(0);
});

<?php

use App\Actions\BuildUserDataExport;
use App\Actions\RequestAccountDeletion;
use App\Models\User;
use App\Models\WebPushSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('data export contains device metadata but no push secrets', function () {
    $user = User::factory()->create();
    WebPushSubscription::factory()->for($user)->create([
        'endpoint' => 'https://push.example.test/private',
        'device_name' => 'Mon téléphone',
    ]);

    $json = json_encode(app(BuildUserDataExport::class)->handle($user), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

    expect($json)->toContain('Mon téléphone')
        ->not->toContain('push.example.test/private');
});

test('requesting account deletion immediately revokes push devices', function () {
    $user = User::factory()->withProfile()->create();
    WebPushSubscription::factory()->for($user)->create();

    app(RequestAccountDeletion::class)->handle($user);

    expect($user->webPushSubscriptions()->whereNotNull('revoked_at')->count())->toBe(1);
});

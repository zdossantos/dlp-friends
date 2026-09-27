<?php

use App\Enums\WebPushPreference;
use App\Models\User;
use App\Models\WebPushSubscription;

test('a member can manage notification preferences and devices on mobile', function () {
    $member = User::factory()->withProfile()->create(['locale' => 'fr']);
    WebPushSubscription::factory()->for($member)->create([
        'device_name' => 'iPhone de test',
        'platform' => 'iOS',
    ]);
    $this->actingAs($member);

    $page = visit('/settings/notifications')->on()->mobile();
    $page->assertSee('iPhone de test')
        ->keys('[data-test="notification-messages-switch"]', 'Space')
        ->press('[data-test="save-notification-preferences"]')
        ->assertAttribute('[data-test="notification-messages-switch"]', 'aria-checked', 'false')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();

    expect($member->notificationPreferences()
        ->where('category', WebPushPreference::Messages)
        ->value('enabled'))->toBeFalse()
        ->and($member->webPushSubscriptions()->whereNull('revoked_at')->count())->toBe(1);
});

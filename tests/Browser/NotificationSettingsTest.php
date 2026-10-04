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
        ->assertDontSee('Administration')
        ->assertDontSee('Arrivée de nouveaux membres')
        ->keys('[data-test="notification-messages-switch"]', 'Space')
        ->press('[data-test="save-notification-preferences"]')
        ->assertSee(__('account.settings.notifications.saved', [], 'fr'))
        ->assertAttribute('[data-test="notification-messages-switch"]', 'aria-checked', 'false')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();

    expect($member->notificationPreferences()
        ->where('category', WebPushPreference::Messages)
        ->value('enabled'))->toBeFalse()
        ->and($member->webPushSubscriptions()->whereNull('revoked_at')->count())->toBe(1);
});

test('an admin can disable new member alerts by keyboard independently of administration push', function (string $locale, string $label) {
    $admin = User::factory()->admin()->create(['locale' => $locale]);
    $this->actingAs($admin);
    $page = visit('/settings/notifications')->on()->mobile();
    $page->assertSee($label)
        ->keys('[data-test="admin-new-member-alerts-switch"]', 'Space')
        ->assertAttribute('[data-test="admin-new-member-alerts-switch"]', 'aria-checked', 'false')
        ->assertScript("new FormData(document.querySelector('form')).getAll('admin_new_member_alerts')", ['0'])
        ->press('[data-test="save-notification-preferences"]')
        ->assertSee(__('account.settings.notifications.saved', [], $locale));
    expect($admin->fresh()->admin_new_member_alerts)->toBeFalse();
    $page
        ->assertAttribute('[data-test="admin-new-member-alerts-switch"]', 'aria-checked', 'false')
        ->assertAttribute('[data-test="notification-administration-switch"]', 'aria-checked', 'true')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
    expect($admin->fresh()->admin_new_member_alerts)->toBeFalse();
    $page->refresh()->assertAttribute('[data-test="admin-new-member-alerts-switch"]', 'aria-checked', 'false');
})->with([
    ['fr', 'Arrivée de nouveaux membres'],
    ['en', 'New member arrivals'],
]);

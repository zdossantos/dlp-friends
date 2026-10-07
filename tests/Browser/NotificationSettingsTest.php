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

test('weekly email preferences can be disabled independently and persist after reload', function (string $locale, string $label) {
    $member = User::factory()->withProfile()->create(['locale' => $locale]);
    $this->actingAs($member);
    $page = visit('/settings/notifications')->on()->mobile();
    $page->assertSee($label)
        ->assertAttribute('[data-test="email-weekly_recap_messages-switch"]', 'aria-checked', 'true')
        ->assertAttribute('[data-test="email-weekly_recap_matches-switch"]', 'aria-checked', 'true')
        ->keys('[data-test="email-weekly_recap_messages-switch"]', 'Space')
        ->press('[data-test="save-notification-preferences"]')
        ->assertSee(__('account.settings.notifications.saved', [], $locale));
    expect($member->fresh()->weekly_recap_messages)->toBeFalse()
        ->and($member->fresh()->weekly_recap_matches)->toBeTrue();
    $page->refresh()
        ->assertAttribute('[data-test="email-weekly_recap_messages-switch"]', 'aria-checked', 'false')
        ->assertAttribute('[data-test="email-weekly_recap_matches-switch"]', 'aria-checked', 'true')
        ->assertAttribute('[data-test="notification-messages-switch"]', 'aria-checked', 'true')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
})->with([
    ['fr', 'Récapitulatif hebdomadaire par e-mail'],
    ['en', 'Weekly email recap'],
]);

test('disable all turns off both weekly email preferences', function () {
    $member = User::factory()->withProfile()->create();
    $this->actingAs($member);
    $page = visit('/settings/notifications');
    $page->script('window.confirm = () => true');
    $page->press('[data-test="disable-all-notifications"]')
        ->assertSee(__('account.settings.notifications.disabled_all'));
    expect($member->fresh()->weekly_recap_messages)->toBeFalse()
        ->and($member->fresh()->weekly_recap_matches)->toBeFalse();
    $page->refresh()
        ->assertAttribute('[data-test="email-weekly_recap_messages-switch"]', 'aria-checked', 'false')
        ->assertAttribute('[data-test="email-weekly_recap_matches-switch"]', 'aria-checked', 'false');
});

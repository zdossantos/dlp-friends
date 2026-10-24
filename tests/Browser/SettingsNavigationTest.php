<?php

use App\Models\Role;
use App\Models\User;

test('members can identify each settings page and return to the previous settings page without horizontal scrolling', function (string $locale, int $width) {
    $member = User::factory()->withProfile()->create(['locale' => $locale]);
    $this->actingAs($member)->withSession(['auth.password_confirmed_at' => time()]);
    $page = visit('/settings/account')->resize($width, 900);

    foreach (['account', 'security', 'appearance', 'notifications'] as $section) {
        $page->click('nav[aria-label="'.__('account.settings.navigation', [], $locale).'"] a[href$="/'.$section.'"]')
            ->assertAttribute('nav[aria-label="'.__('account.settings.navigation', [], $locale).'"] a[aria-current="page"]', 'href', '/settings/'.$section)
            ->assertScript('document.documentElement.scrollWidth <= innerWidth', true)
            ->assertVisible('[data-test="settings-back-to-profile"]')
            ->assertNoJavaScriptErrors();
        if ($locale === 'fr') {
            $page->screenshot(fullPage: false, filename: "settings-{$section}-{$width}");
            $page->script("document.documentElement.classList.add('dark')");
            $page->screenshot(fullPage: false, filename: "settings-{$section}-{$width}-dark");
            $page->script("document.documentElement.classList.remove('dark')");
        }
    }

    $page->click('[data-test="settings-back-to-profile"]')
        ->assertPathIs('/settings/appearance')
        ->forward()->assertPathIs('/settings/notifications');
})->with([
    'French mobile' => ['fr', 390],
    'English narrow mobile' => ['en', 320],
    'French desktop' => ['fr', 1280],
    'English desktop' => ['en', 1280],
]);

test('partner only accounts return from notification settings to their partner profile', function () {
    $this->actingAs(User::factory()->partnerOnly()->create());

    visit('/settings/notifications')
        ->assertAttribute('[data-test="settings-back-to-profile"]', 'href', '/partner/profile')
        ->assertVisible('nav[aria-label="Réglages"] a[href="/settings/account"]')
        ->click('[data-test="settings-back-to-profile"]')
        ->assertPathIs('/partner/profile')
        ->assertNoJavaScriptErrors();
});

test('administrator only accounts return from notification settings to administration', function () {
    $administrator = User::factory()->admin()->create();
    $administrator->roles()->detach(Role::query()->where('name', 'user')->firstOrFail());
    $this->actingAs($administrator);

    visit('/settings/notifications')
        ->assertAttribute('[data-test="settings-back-to-profile"]', 'href', '/dashboard')
        ->click('[data-test="settings-back-to-profile"]')
        ->assertPathIs('/dashboard')
        ->assertNoJavaScriptErrors();
});

test('history return survives a reload', function () {
    $this->actingAs(User::factory()->withProfile()->create());

    visit('/settings/account')
        ->click('nav a[href$="/appearance"]')->assertPathIs('/settings/appearance')
        ->refresh()
        ->click('[data-test="settings-back-to-profile"]')->assertPathIs('/settings/account')
        ->forward()->assertPathIs('/settings/appearance')
        ->assertNoJavaScriptErrors();
});

test('a direct entry uses its fallback even when the browser already has history', function () {
    $this->actingAs(User::factory()->withProfile()->create());

    visit('/settings/account')->navigate('/settings/notifications')
        ->assertScript('history.length > 1', true)
        ->click('[data-test="settings-back-to-profile"]')->assertPathIs('/profile')
        ->assertNoJavaScriptErrors();
});

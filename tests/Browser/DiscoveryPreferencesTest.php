<?php

use App\Models\Interest;
use App\Models\User;

test('a member changes both discovery filters from account settings by keyboard on mobile', function (string $locale) {
    $member = User::factory()->withProfile()->create(['locale' => $locale]);
    $interest = Interest::factory()->create();
    $member->profile->interests()->attach($interest);
    $eligible = User::factory()->withProfile()->create();
    $eligible->profile->interests()->attach($interest);
    User::factory()->withProfile()->create();
    $this->actingAs($member);

    $minSelector = '[role="slider"][aria-label="'.__('account.settings.discovery.min_age', [], $locale).'"]';
    $maxSelector = '[role="slider"][aria-label="'.__('account.settings.discovery.max_age', [], $locale).'"]';
    $page = visit('/settings/account')->on()->mobile();
    $page->assertSee(__('account.settings.discovery.title', [], $locale))
        ->assertAttribute($minSelector, 'aria-valuenow', '20')
        ->assertAttribute($maxSelector, 'aria-valuenow', '30')
        ->keys($minSelector, 'ArrowRight')
        ->keys($maxSelector, 'ArrowLeft')
        ->fill('min_common_interests', '1')
        ->press('[data-test="save-discovery-preferences"]')
        ->assertSee(__('account.settings.discovery.saved', [], $locale));

    expect($member->fresh()->discoveryPreferences())->toBe([
        'min_age' => 21, 'max_age' => 29, 'min_common_interests' => 1,
    ]);
    $page->refresh()
        ->assertAttribute($minSelector, 'aria-valuenow', '21')
        ->assertAttribute($maxSelector, 'aria-valuenow', '29')
        ->assertValue('[name="min_common_interests"]', '1')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors()
        ->screenshotElement('[aria-labelledby="discovery-preferences-title"]', filename: "discovery-preferences-{$locale}");

    $page->navigate('/discover')
        ->assertPresent('[data-test="discovery-card-stack-item"]')
        ->assertScript('document.querySelectorAll("[data-test=discovery-card-stack-item]").length', 1);
    $page->navigate('/settings/account')
        ->fill('min_common_interests', '2')
        ->press('[data-test="save-discovery-preferences"]')
        ->assertSee(__('account.settings.discovery.saved', [], $locale))
        ->navigate('/discover')
        ->assertSee(__('discovery.page.empty_title', [], $locale))
        ->assertSee(__('discovery.page.empty_description', [], $locale))
        ->assertNoJavaScriptErrors();
})->with(['fr', 'en']);

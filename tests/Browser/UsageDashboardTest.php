<?php

use App\Models\MemberMatch;
use App\Models\Message;
use App\Models\Swipe;
use App\Models\User;

test('usage dashboard filters dates and exposes accessible chart data without mobile overflow', function (int $width, int $height) {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->withProfile()->create();
    $other = User::factory()->withProfile()->create();
    $match = MemberMatch::query()->create(['user_low_id' => $member->id, 'user_high_id' => $other->id]);
    $conversation = $match->conversation()->create();
    foreach ([1, 2, 2, 4, 5, 5, 5] as $days) {
        Message::factory()->create(['conversation_id' => $conversation->id, 'author_user_id' => $member->id, 'created_at' => now('UTC')->subDays($days)]);
    }
    Swipe::query()->forceCreate(['actor_user_id' => $member->id, 'target_user_id' => $other->id, 'decision' => 'like', 'created_at' => now('UTC')->subDays(1)]);
    $this->actingAs($admin);

    $page = visit('/dashboard')->resize($width, $height)
        ->assertSee('Membres contributeurs')
        ->assertPresent('[data-test="usage-chart-overview"]')
        ->assertNotPresent('input[type="range"]')
        ->assertPresent('[data-test="usage-donut-discovery"] svg')
        ->assertScript('document.querySelectorAll(\'[data-test="usage-chart-overview"] button\').length', 14)
        ->assertPresent('[data-test="usage-chart-messaging"]')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();

    $page->click('[data-test="usage-preset-7"]')
        ->assertQueryStringHas('from', now('UTC')->subDays(6)->toDateString())
        ->assertQueryStringHas('to', now('UTC')->toDateString())
        ->click('[data-test="usage-chart-discovery"] button:nth-of-type(2)')
        ->assertAttribute('[data-test="usage-chart-discovery"] button:nth-of-type(2)', 'aria-pressed', 'false')
        ->click('[data-test="usage-chart-discovery"] button:nth-of-type(3)')
        ->assertDisabled('[data-test="usage-chart-discovery"] button:first-of-type')
        ->click('[data-test="usage-chart-discovery"] button:nth-of-type(2)')
        ->assertEnabled('[data-test="usage-chart-discovery"] button:first-of-type')
        ->click('[data-test="usage-chart-growth"] summary')
        ->assertVisible('[data-test="usage-chart-growth"] table')
        ->assertSee('État actuel des inscrits de la période')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'usage-dashboard-'.$width);

    $page->click('[data-test="usage-range-trigger"]')
        ->assertVisible('[data-test="usage-calendar"]')
        ->click('[data-test="usage-prev-month"]')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->click('[data-test="usage-date-'.now('UTC')->subDays(6)->subMonthNoOverflow()->startOfMonth()->addDay()->toDateString().'"]')
        ->click('[data-test="usage-date-'.now('UTC')->subDays(6)->subMonthNoOverflow()->startOfMonth()->addDays(3)->toDateString().'"]')
        ->assertQueryStringHas('from', now('UTC')->subDays(6)->subMonthNoOverflow()->startOfMonth()->addDay()->toDateString())
        ->assertQueryStringHas('to', now('UTC')->subDays(6)->subMonthNoOverflow()->startOfMonth()->addDays(3)->toDateString());

    $page->fill('#usage-from', now('UTC')->subDays(2)->toDateString())
        ->fill('#usage-to', now('UTC')->toDateString())
        ->press('Appliquer les dates')
        ->assertQueryStringHas('from', now('UTC')->subDays(2)->toDateString())
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
})->with([[320, 700], [1440, 900]]);

test('English usage dashboard renders empty activity without invalid percentages', function () {
    $this->actingAs(User::factory()->admin()->create(['locale' => 'en']));

    visit('/dashboard')->on()->mobile()
        ->assertSee('Contributing members')
        ->assertSee('No activity recorded in this period.')
        ->assertSee('Current community')
        ->assertDontSee('NaN')
        ->assertDontSee('Infinity')
        ->assertNoJavaScriptErrors();
});

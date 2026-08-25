<?php

use App\Models\User;

test('a stored appearance takes precedence over the system preference', function () {
    $user = User::factory()->withProfile()->create();
    $this->actingAs($user);

    $page = visit('/settings/appearance')->inDarkMode();
    $page->script("localStorage.setItem('appearance', 'light')");
    $page->navigate('/settings/appearance')
        ->assertScript("localStorage.getItem('appearance')", 'light')
        ->assertScript("document.documentElement.classList.contains('dark')", false)
        ->assertNoJavaScriptErrors();
});

test('the system appearance is used when no preference is stored', function () {
    $user = User::factory()->withProfile()->create();
    $this->actingAs($user);

    $page = visit('/settings/appearance')->inDarkMode();
    $page->script("localStorage.removeItem('appearance')");
    $page->navigate('/settings/appearance')
        ->assertScript("localStorage.getItem('appearance')", null)
        ->assertScript("document.documentElement.classList.contains('dark')", true)
        ->assertNoJavaScriptErrors();
});

test('appearance remains stable across repeated Inertia navigation', function () {
    $user = User::factory()->withProfile()->create();
    $this->actingAs($user);

    $page = visit('/settings/appearance')->inDarkMode()
        ->click('Sombre')
        ->assertScript("localStorage.getItem('appearance')", 'dark')
        ->assertScript("document.documentElement.classList.contains('dark')", true);

    foreach (['/settings/account', '/settings/appearance', '/settings/account', '/settings/appearance'] as $url) {
        $page->navigate($url)
            ->assertScript("localStorage.getItem('appearance')", 'dark')
            ->assertScript("document.documentElement.classList.contains('dark')", true);
    }

    $page->click('Système')
        ->assertScript("localStorage.getItem('appearance')", 'system')
        ->assertScript("document.documentElement.classList.contains('dark')", true)
        ->navigate('/settings/account')
        ->assertScript("document.documentElement.classList.contains('dark')", true)
        ->navigate('/settings/appearance')
        ->assertScript("localStorage.getItem('appearance')", 'system')
        ->assertScript("document.documentElement.classList.contains('dark')", true)
        ->assertNoJavaScriptErrors();
});

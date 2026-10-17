<?php

use App\Http\Middleware\EnsureDeploymentReady;

test('shows beta badge in each theme', function () {
    app()->instance('env', 'beta');
    $this->withoutMiddleware(EnsureDeploymentReady::class);
    $page = visit('/login')->assertSee('Beta')->assertPresent('[data-test="beta-badge"]');

    foreach (['', 'dark', 'dark seasonal-halloween'] as $classes) {
        $page->script("document.documentElement.className = '{$classes}'");
        $page->assertScript("getComputedStyle(document.querySelector('[data-test=beta-badge]')).backgroundColor", 'rgb(255, 255, 255)')
            ->assertScript("getComputedStyle(document.querySelector('[data-test=beta-badge]')).position", 'absolute')
            ->assertNoJavaScriptErrors();
    }
});

test('does not badge production', function () {
    app()->instance('env', 'production');
    $this->withoutMiddleware(EnsureDeploymentReady::class);
    visit('/login')->assertMissing('[data-test="beta-badge"]')->assertNoJavaScriptErrors();
});

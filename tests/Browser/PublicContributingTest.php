<?php

test('guests can reach the contribution guide from the landing footer on mobile and desktop', function (string $locale, string $path, string $heading, int $width) {
    $page = visit('/'.$locale)->resize($width, 900);

    $page->click('[data-test="landing-contributing-footer"]')
        ->assertPathIs($path)
        ->assertSee($heading)
        ->assertPresent('a[href="https://github.com/zdossantos/dlp-friends/fork"]')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoAccessibilityIssues()
        ->assertNoJavaScriptErrors();

    $page->click('a[hreflang="'.($locale === 'fr' ? 'en' : 'fr').'"]')
        ->assertPathIs($locale === 'fr' ? '/en/contribute' : '/fr/contribuer');
})->with([
    ['fr', '/fr/contribuer', 'Contribuer à DLP Friends', 390],
    ['fr', '/fr/contribuer', 'Contribuer à DLP Friends', 1440],
    ['en', '/en/contribute', 'Contribute to DLP Friends', 390],
    ['en', '/en/contribute', 'Contribute to DLP Friends', 1440],
]);

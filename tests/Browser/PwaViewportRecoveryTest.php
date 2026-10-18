<?php

use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('a resumed iOS standalone app automatically restores its native scroll extent after resuming', function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->withProfile()->create());
    $page = visit('/profile')->resize(402, 812);
    $page->page()->evaluate(<<<'JS'
        (() => {
            Object.defineProperty(navigator, 'standalone', { configurable: true, value: true });
            Object.defineProperty(navigator, 'userAgent', { configurable: true, value: 'Mozilla/5.0 (iPhone) AppleWebKit/605.1.15' });
            Object.defineProperty(window, 'innerHeight', { configurable: true, value: 682 });
            window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: true }));
        })()
    JS);
    $page->assertScript('document.documentElement.scrollHeight > 812', true);
    $page->page()->evaluate('delete window.innerHeight');
    $page->wait(1.2)
        ->assertScript('document.documentElement.scrollHeight > 812', true)
        ->assertScript('document.querySelector(\'[data-test="member-shell-content"]\').getBoundingClientRect().height', 812)
        ->assertMissing('[data-test="viewport-diagnostics"]')
        ->assertNoJavaScriptErrors();
});

test('viewport recovery leaves browser zoom and focused form fields alone', function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->withProfile()->create());
    $page = visit('/profile')->resize(402, 812);
    $page->page()->evaluate(<<<'JS'
        (() => {
            Object.defineProperty(navigator, 'userAgent', { configurable: true, value: 'Mozilla/5.0 (iPhone) AppleWebKit/605.1.15' });
            Object.defineProperty(window, 'innerHeight', { configurable: true, value: 682 });
            window.dispatchEvent(new Event('focus'));
        })()
    JS);
    $page->wait(0.5)->assertScript('document.documentElement.style.getPropertyValue("--pwa-scroll-extent")', '');
    $page->page()->evaluate(<<<'JS'
        (() => {
            Object.defineProperty(navigator, 'standalone', { configurable: true, value: true });
            Object.defineProperty(window.visualViewport, 'scale', { configurable: true, value: 1.6 });
            window.dispatchEvent(new Event('resize'));
        })()
    JS);
    $page->wait(0.5)->assertScript('document.documentElement.style.getPropertyValue("--pwa-scroll-extent")', '');
    $page->page()->evaluate(<<<'JS'
        (() => {
            Object.defineProperty(window.visualViewport, 'scale', { configurable: true, value: 1 });
            const input = document.createElement('input');
            document.body.append(input);
            input.focus();
            window.dispatchEvent(new Event('resize'));
        })()
    JS);
    $page->wait(0.5)->assertScript('document.documentElement.style.getPropertyValue("--pwa-scroll-extent")', '');
    $page->page()->evaluate('document.activeElement.blur()');
    $page->assertScript('document.documentElement.scrollHeight > 812', true)->assertNoJavaScriptErrors();
});

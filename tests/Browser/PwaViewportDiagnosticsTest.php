<?php

use App\Http\Middleware\EnsureDeploymentReady;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('manual viewport reflow records the experiment and restores document geometry', function () {
    app()->instance('env', 'beta');
    $this->withoutMiddleware(EnsureDeploymentReady::class);
    $this->actingAs(User::factory()->admin()->withProfile()->create());

    $page = visit('/notifications')->resize(390, 844);
    $page->click('[data-test="viewport-diagnostics-start"]')
        ->assertMissing('[data-test="viewport-reflow-probe"]')
        ->click('[data-test="viewport-diagnostics-reflow"]')
        ->assertScript('document.documentElement.scrollHeight > 844', true)
        ->assertScript(<<<'JS'
            (() => {
                const samples = JSON.parse(document.querySelector('[data-test="viewport-report"]').textContent).samples;
                return samples.some(sample => sample.event === 'reflow:before')
                    && samples.some(sample => sample.event === 'reflow:expanded' && sample.document.scrollHeight > 844);
            })()
        JS, true)
        ->assertMissing('[data-test="viewport-reflow-probe"]')
        ->assertScript(<<<'JS'
            (() => {
                const samples = JSON.parse(document.querySelector('[data-test="viewport-report"]').textContent).samples;
                return samples.some(sample => sample.event === 'reflow:restored' && sample.document.scrollHeight === 844)
                    && document.documentElement.scrollHeight === 844
                    && document.querySelector('meta[name="viewport"]').content === 'width=device-width, initial-scale=1'
                    && document.querySelector('meta[name="apple-mobile-web-app-status-bar-style"]').content === 'default';
            })()
        JS, true)
        ->click('[data-test="viewport-diagnostics-reflow"]')
        ->assertPresent('[data-test="viewport-reflow-probe"]')
        ->click('[data-test="viewport-diagnostics-stop"]')
        ->assertMissing('[data-test="viewport-reflow-probe"]')
        ->assertScript('document.documentElement.scrollHeight', 844)
        ->assertNoJavaScriptErrors();
});

test('viewport diagnostics are opt-in and only available on beta', function (string $environment, bool $visible) {
    app()->instance('env', $environment);
    $this->withoutMiddleware(EnsureDeploymentReady::class);
    $this->actingAs(User::factory()->admin()->withProfile()->create());

    $page = visit('/notifications')->resize(390, 844);

    if ($visible) {
        $page->assertPresent('[data-test="viewport-diagnostics-start"]')
            ->assertMissing('[data-test="viewport-report"]');
    } else {
        $page->assertMissing('[data-test="viewport-diagnostics"]');
    }
})->with([['beta', true], ['production', false]]);

test('viewport diagnostics compare rotations without recording account or page content', function () {
    app()->instance('env', 'beta');
    $this->withoutMiddleware(EnsureDeploymentReady::class);
    $user = User::factory()->admin()->withProfile()->create();
    $this->actingAs($user);

    $page = visit('/notifications')->resize(390, 844);
    $geometry = $page->page()->evaluate(<<<'JS'
        (() => ({
            scrollHeight: document.documentElement.scrollHeight,
            navigation: document.querySelector('[data-test="member-bottom-navigation"]').getBoundingClientRect().toJSON(),
            shell: document.querySelector('[data-test="member-shell-content"]').getBoundingClientRect().toJSON(),
        }))()
    JS);

    $page->click('[data-test="viewport-diagnostics-start"]')
        ->assertPresent('[data-test="viewport-report"]')
        ->assertScript(<<<'JS'
            (() => {
                const sample = JSON.parse(document.querySelector('[data-test="viewport-report"]').textContent).samples.at(-1);
                return sample.window.width === 390 && sample.window.height === 844
                    && sample.css.svh === 844 && sample.navigation.bottom > 700;
            })()
        JS, true);
    expect($page->page()->evaluate(<<<'JS'
        (() => ({
            scrollHeight: document.documentElement.scrollHeight,
            navigation: document.querySelector('[data-test="member-bottom-navigation"]').getBoundingClientRect().toJSON(),
            shell: document.querySelector('[data-test="member-shell-content"]').getBoundingClientRect().toJSON(),
        }))()
    JS))->toEqual($geometry);

    $page->resize(844, 390)
        ->assertScript(<<<'JS'
            (() => {
                const samples = JSON.parse(document.querySelector('[data-test="viewport-report"]').textContent).samples;
                return samples.some(sample => sample.event === 'resize' && sample.window.width === 844 && sample.window.height === 390);
            })()
        JS, true)
        ->click('[data-test="viewport-diagnostics-capture"]')
        ->assertScript(<<<'JS'
            (() => {
                const samples = JSON.parse(document.querySelector('[data-test="viewport-report"]').textContent).samples;
                return samples.some(sample => sample.window.width === 390 && sample.window.height === 844)
                    && samples.at(-1).window.width === 844 && samples.at(-1).window.height === 390
                    && samples.at(-1).css.svh === 390;
            })()
        JS, true);

    $report = $page->page()->evaluate('document.querySelector(\'[data-test="viewport-report"]\').textContent');
    expect($report)->not->toContain($user->email, $user->profile->display_name, '/notifications');

    $page->page()->evaluate(<<<'JS'
        (() => {
            for (let index = 0; index < 60; index++) window.dispatchEvent(new Event('focus'));
            return true;
        })()
    JS);
    $page->assertScript(<<<'JS'
        (() => {
            const samples = JSON.parse(document.querySelector('[data-test="viewport-report"]').textContent).samples;
            return samples.length === 40 && samples.at(-1).event === 'focus';
        })()
    JS, true);

    $page->click('[data-test="viewport-diagnostics-stop"]')
        ->assertMissing('[data-test="viewport-report"]')
        ->click('[data-test="viewport-diagnostics-start"]')
        ->assertScript(<<<'JS'
            (() => {
                const samples = JSON.parse(document.querySelector('[data-test="viewport-report"]').textContent).samples;
                return samples.length === 1 && samples[0].event === 'start';
            })()
        JS, true)
        ->assertNoJavaScriptErrors();
});

test('a resumed iOS standalone app automatically restores its native scroll extent without opening diagnostics', function () {
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
        ->assertMissing('[data-test="viewport-report"]')
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

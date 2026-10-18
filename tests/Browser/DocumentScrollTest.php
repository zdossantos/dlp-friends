<?php

use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

test('member pages let the document scroll while navigation stays at the viewport bottom', function (string $url) {
    $this->actingAs(User::factory()->admin()->withProfile()->create());

    $page = visit($url)->resize(390, 844)
        ->assertPresent('[data-test="member-shell-content"]');
    $page->page()->evaluate(<<<'JS'
        (() => {
            const content = document.querySelector('[data-test="member-shell-content"]');
            const extraContent = document.createElement('div');
            extraContent.style.cssText = 'height: 1600px; flex-shrink: 0';
            content.append(extraContent);
            window.scrollTo(0, 400);
        })()
    JS);
    $page->assertScript(<<<'JS'
        (() => {
            const content = document.querySelector('[data-test="member-shell-content"]');
            const navigation = document.querySelector('[data-test="member-bottom-navigation-container"]');
            return window.scrollY > 0 && content.scrollTop === 0
                && content.scrollHeight <= content.clientHeight + 1
                && Math.abs(navigation.getBoundingClientRect().bottom - window.innerHeight) <= 1;
        })()
    JS, true)->resize(844, 390)->resize(390, 812)
        ->assertScript(<<<'JS'
            (() => {
                const navigation = document.querySelector('[data-test="member-bottom-navigation-container"]');
                return Math.abs(navigation.getBoundingClientRect().bottom - window.innerHeight) <= 1;
            })()
        JS, true)->assertNoJavaScriptErrors();
})->with(['/notifications', '/events', '/settings/account']);

test('navigation switches between document pages and a bounded profile without trapping scrolling', function () {
    $this->actingAs(User::factory()->admin()->withProfile()->create());

    $page = visit('/notifications')->resize(390, 844)
        ->click('a[href="/profile"]')
        ->assertPresent('[data-test="profile-page"]')
        ->assertScript(<<<'JS'
            (() => {
                const shell = document.querySelector('[data-test="member-shell-content"]');
                return shell.getBoundingClientRect().height === window.innerHeight
                    && document.documentElement.scrollHeight <= window.innerHeight;
            })()
        JS, true)
        ->resize(390, 682)->resize(390, 812)
        ->assertScript('document.querySelector(\'[data-test="member-shell-content"]\').getBoundingClientRect().height', 812)
        ->click('a[href="/events"]')
        ->assertSee('Événements amicaux');

    $page->page()->evaluate(<<<'JS'
        (() => {
            const content = document.querySelector('[data-test="member-shell-content"]');
            const extraContent = document.createElement('div');
            extraContent.style.cssText = 'height: 1600px; flex-shrink: 0';
            content.append(extraContent);
            window.scrollTo(0, 400);
        })()
    JS);
    $page->assertScript('window.scrollY > 0', true)->assertNoJavaScriptErrors();
});

test('profile editing keeps the wizard footer visible on a short mobile viewport', function () {
    $this->actingAs(User::factory()->withProfile()->create());

    visit('/profile/edit')->resize(390, 682)
        ->click('Suivant')
        ->assertPresent('[data-test="profile-step-content-2"]')
        ->assertScript(<<<'JS'
            (() => {
                const footer = document.querySelector('[data-test="profile-form-footer"]').getBoundingClientRect();
                const step = document.querySelector('[data-test="profile-step-content-2"]');
                return footer.bottom <= window.innerHeight && footer.top >= 0
                    && document.documentElement.scrollHeight <= window.innerHeight
                    && step.scrollHeight > step.clientHeight;
            })()
        JS, true)->assertNoJavaScriptErrors();
});

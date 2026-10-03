<?php

use App\Models\User;

test('admin content scrolls without moving the viewport or bottom navigation', function (int $width, int $height) {
    $admin = User::factory()->admin()->create();
    User::factory()->count(10)->create();
    $this->actingAs($admin);

    $page = visit('/dashboard')->resize($width, $height)
        ->assertPresent('[data-test="admin-bottom-navigation"]');
    $page->page()->evaluate(<<<'JS'
        (() => {
            document.documentElement.classList.add('seasonal-halloween');
            const shell = document.querySelector('[data-test="admin-shell-content"]');
            window.__adminNavigationBottom = document.querySelector('[data-test="admin-bottom-navigation"]').getBoundingClientRect().bottom;
            shell.scrollTop = 400;
            return true;
        })()
    JS);
    $page->assertScript(<<<'JS'
        (() => {
            const shell = document.querySelector('[data-test="admin-shell-content"]');
            const navigation = document.querySelector('[data-test="admin-bottom-navigation"]');
            return shell.scrollTop > 0 && window.scrollY === 0
                && Math.abs(navigation.getBoundingClientRect().bottom - window.__adminNavigationBottom) <= 1
                && navigation.getBoundingClientRect().bottom <= window.innerHeight;
        })()
    JS, true);
    $page->page()->evaluate(<<<'JS'
        (() => {
            const shell = document.querySelector('[data-test="admin-shell-content"]');
            shell.scrollTop = shell.scrollHeight;
            return true;
        })()
    JS);
    $page->assertScript(<<<'JS'
        (() => {
            const shell = document.querySelector('[data-test="admin-shell-content"]');
            const navigation = document.querySelector('[data-test="admin-bottom-navigation"]');
            const lastCard = shell.lastElementChild;
            return Math.abs(shell.scrollHeight - shell.clientHeight - shell.scrollTop) <= 1
                && lastCard.getBoundingClientRect().bottom <= navigation.getBoundingClientRect().top
                && document.documentElement.scrollHeight <= window.innerHeight;
        })()
    JS, true)->assertNoJavaScriptErrors();
})->with([[320, 700], [390, 844]]);

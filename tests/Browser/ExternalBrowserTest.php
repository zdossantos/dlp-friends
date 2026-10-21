<?php

test('browser help offers a manual copy when clipboard access is refused', function () {
    $page = visit('/fr/open')->on()->mobile()
        ->assertSee('Ouvre DLP Friends dans ton navigateur');
    $page->script(<<<'JS'
        Object.defineProperty(navigator, 'clipboard', {
            configurable: true,
            value: { writeText: async () => { throw new Error('denied'); } },
        });
        JS);
    $page->click('[data-copy-link]')
        ->assertSee('Sélectionne le lien ci-dessus')
        ->assertScript('document.activeElement.id', 'browser-link')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
});

test('browser help confirms copying only after the clipboard accepts the link', function () {
    $page = visit('/en/open')->on()->mobile()
        ->assertSee('Open DLP Friends in your browser');
    $page->script(<<<'JS'
        Object.defineProperty(navigator, 'clipboard', {
            configurable: true,
            value: { writeText: async (value) => { window.copiedBrowserLink = value; } },
        });
        JS);
    $page->click('[data-copy-link]')->assertSee('Link copied');
    expect($page->script('window.copiedBrowserLink'))
        ->toBe($page->script('document.querySelector("#browser-link").value'));
    $page->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)->assertNoJavaScriptErrors();
});

test('a regular browser can change language and continue to the site without returning to browser help', function () {
    $page = visit('/fr/open');
    $page->click('EN')->assertSee('Open DLP Friends in your browser')
        ->click('[data-continue]')
        ->assertPathIs('/en')
        ->assertNoJavaScriptErrors();
});

test('browser help adapts its opening action to the phone', function (string $userAgent, string $scheme) {
    config()->set('app.url', 'https://dlp.example');
    $page = visit('/fr/open')->withUserAgent($userAgent)->on()->iPhone15Pro();
    $page->assertSee('Ouvrir dans le navigateur')
        ->assertScript("document.querySelector('[data-open-browser]').href.startsWith('{$scheme}')", true)
        ->assertPresent('[data-copy-link]')
        ->assertNoJavaScriptErrors();
})->with([
    'Safari' => ['iPhone Safari', 'x-safari-https://'],
    'Instagram iOS' => ['iPhone Instagram', 'instagram://extbrowser/?url='],
    'Instagram Android' => ['Android Instagram', 'intent://'],
]);

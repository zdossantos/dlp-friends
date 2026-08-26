<?php

test('a visitor changes locale from the public language selector', function () {
    visit('/')
        ->assertVisible('[data-test="locale-switcher"]')
        ->select('locale', 'en')
        ->assertScript('document.documentElement.lang', 'en')
        ->assertNoJavaScriptErrors();
});

<?php

test('the public application boots in a real browser', function () {
    visit('/')
        ->assertNoJavaScriptErrors()
        ->assertSee('DLP Friends');
});

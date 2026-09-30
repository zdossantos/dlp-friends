<?php

test('public pages expose sanitized analytics metadata without loading Google before consent', function () {
    config()->set('services.google.analytics_id', 'G-TEST123456');

    $this->get('/fr')
        ->assertOk()
        ->assertSee('data-analytics-page-type="landing"', false)
        ->assertSee('data-analytics-page-title="DLP Friends', false)
        ->assertSee('data-analytics-locale="fr"', false)
        ->assertDontSee('googletagmanager.com/gtag/js', false);

    $this->get('/fr/matching')
        ->assertOk()
        ->assertSee('data-analytics-page-type="matching_explainer"', false)
        ->assertSee('data-analytics-locale="fr"', false);
});

test('private application pages are marked as spa analytics surfaces', function () {
    config()->set('services.google.analytics_id', 'G-TEST123456');

    $this->get('/login')
        ->assertOk()
        ->assertSee('data-analytics-spa="true"', false)
        ->assertSee('data-analytics-page-type="application"', false);
});

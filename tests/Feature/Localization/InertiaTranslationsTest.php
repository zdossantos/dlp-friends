<?php

use Inertia\Testing\AssertableInertia as Assert;

test('Inertia shares the English frontend catalogue selected by the request', function () {
    $this->withHeader('Accept-Language', 'en-US,en;q=0.9')
        ->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->where('i18n.locale', 'en')
            ->where('i18n.messages.navigation.settings', 'Settings')
            ->where('i18n.messages.locale.label', 'Language'));
});

test('Inertia shares the French frontend catalogue by default', function () {
    $this->withHeader('Accept-Language', 'de-DE,de;q=0.9')->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->where('i18n.locale', 'fr')
            ->where('i18n.messages.navigation.settings', 'Réglages')
            ->where('i18n.messages.locale.label', 'Langue'));
});

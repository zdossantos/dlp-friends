<?php

use Inertia\Testing\AssertableInertia as Assert;

test('Inertia shares the English frontend catalogue selected by the request', function () {
    $this->withHeader('Accept-Language', 'en-US,en;q=0.9')
        ->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->where('i18n.locale', 'en')
            ->where('i18n.messages.navigation.settings', 'Settings')
            ->where('i18n.messages.navigation.conversations', 'Conversations')
            ->where('i18n.messages.locale.label', 'Language')
            ->where('i18n.messages.copy.Créer mon profil', 'Create my profile')
            ->where('i18n.messages.copy.Supprimer le compte', 'Delete account'));
});

test('Inertia shares the French frontend catalogue by default', function () {
    $this->withHeader('Accept-Language', 'de-DE,de;q=0.9')->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->where('i18n.locale', 'fr')
            ->where('i18n.messages.navigation.settings', 'Réglages')
            ->where('i18n.messages.navigation.conversations', 'Échanges')
            ->where('i18n.messages.locale.label', 'Langue')
            ->where('i18n.messages.copy', []));
});

test('the legacy English copy catalogue contains no empty translations', function () {
    $copy = require lang_path('en/frontend.php');

    expect($copy['copy'])
        ->not->toBeEmpty()
        ->each->toBeString()->not->toBeEmpty();
});

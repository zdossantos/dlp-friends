<?php

use App\Support\FrontendTranslations;
use Inertia\Testing\AssertableInertia as Assert;

function featureTranslationKeys(string $locale): array
{
    $domains = [
        'common',
        'account',
        'profile',
        'onboarding',
        'discovery',
        'conversations',
        'blocking',
        'administration',
    ];
    $flatten = function (array $items, string $prefix = '') use (&$flatten): array {
        $keys = [];

        foreach ($items as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            $keys = is_array($value)
                ? [...$keys, ...$flatten($value, $path)]
                : [...$keys, $path];
        }

        return $keys;
    };
    $keys = [];

    foreach ($domains as $domain) {
        $messages = require lang_path("{$locale}/{$domain}.php");
        $keys = [...$keys, ...array_map(
            fn (string $key): string => "{$domain}.{$key}",
            $flatten($messages),
        )];
    }

    sort($keys);

    return $keys;
}

test('Inertia shares the English frontend catalogue selected by the request', function () {
    $this->withHeader('Accept-Language', 'en-US,en;q=0.9')
        ->get('/login')
        ->assertInertia(fn (Assert $page) => $page
            ->where('i18n.locale', 'en')
            ->where('i18n.messages.common.locale.label', 'Language')
            ->where('i18n.messages.account.email_delivery.rate_limited', 'Too many requests have been made. Please wait one minute before trying again.')
            ->where('i18n.messages.onboarding.page_title', 'Getting started')
            ->where('i18n.messages.onboarding.instructions.reject', 'Pass on this profile to continue.')
            ->where('i18n.messages.administration.onboarding.page_title', 'Product tutorial'));
});

test('Inertia shares translations grouped by business feature', function () {
    $this->withHeader('Accept-Language', 'en-US,en;q=0.9')
        ->get('/login')
        ->assertInertia(fn (Assert $page) => $page
            ->where('i18n.locale', 'en')
            ->where('i18n.messages.common.locale.label', 'Language')
            ->where('i18n.messages.discovery.actions.discover', 'Discover')
            ->where('i18n.messages.discovery.match.title', 'Your worlds cross paths')
            ->where('i18n.messages.profile.interests.title', 'Favorite worlds'));
});

test('French and English business feature catalogues expose identical leaf keys', function () {
    expect(featureTranslationKeys('fr'))->toBe(featureTranslationKeys('en'));
});

test('Inertia shares the French frontend catalogue by default', function () {
    $this->withHeader('Accept-Language', 'de-DE,de;q=0.9')->get('/login')
        ->assertInertia(fn (Assert $page) => $page
            ->where('i18n.locale', 'fr')
            ->where('i18n.messages.common.locale.label', 'Langue')
            ->where('i18n.messages.account.email_delivery.rate_limited', 'Trop de demandes ont été effectuées. Patiente une minute avant de réessayer.')
            ->where('i18n.messages.onboarding.page_title', 'Prise en main')
            ->where('i18n.messages.onboarding.instructions.reject', 'Passe ce profil pour continuer.')
            ->where('i18n.messages.administration.onboarding.page_title', 'Tutoriel produit'));
});

test('only business feature catalogues are shared with the frontend', function () {
    expect(array_keys(FrontendTranslations::messages()))->toBe([
        'common', 'account', 'profile', 'onboarding', 'discovery',
        'conversations', 'blocking', 'administration',
    ]);
});

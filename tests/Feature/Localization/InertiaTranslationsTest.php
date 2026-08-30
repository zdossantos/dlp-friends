<?php

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
        ->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->where('i18n.locale', 'en')
            ->where('i18n.messages.navigation.settings', 'Settings')
            ->where('i18n.messages.navigation.conversations', 'Conversations')
            ->where('i18n.messages.locale.label', 'Language')
            ->where('i18n.messages.mail.rate_limited', 'Too many requests have been made. Please wait one minute before trying again.')
            ->where('i18n.messages.onboarding.page_title', 'Getting started')
            ->where('i18n.messages.onboarding.reject_instruction', 'Pass on this profile to continue.')
            ->where('i18n.messages.admin_onboarding.page_title', 'Product tutorial')
            ->where('i18n.messages.copy.Créer mon profil', 'Create my profile')
            ->where('i18n.messages.copy.Supprimer le compte', 'Delete account'));
});

test('Inertia shares translations grouped by business feature', function () {
    $this->withHeader('Accept-Language', 'en-US,en;q=0.9')
        ->get('/')
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
    $this->withHeader('Accept-Language', 'de-DE,de;q=0.9')->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->where('i18n.locale', 'fr')
            ->where('i18n.messages.navigation.settings', 'Réglages')
            ->where('i18n.messages.navigation.conversations', 'Échanges')
            ->where('i18n.messages.locale.label', 'Langue')
            ->where('i18n.messages.mail.rate_limited', 'Trop de demandes ont été effectuées. Veuillez patienter une minute avant de réessayer.')
            ->where('i18n.messages.onboarding.page_title', 'Prise en main')
            ->where('i18n.messages.onboarding.reject_instruction', 'Passez ce profil pour continuer.')
            ->where('i18n.messages.admin_onboarding.page_title', 'Tutoriel produit')
            ->where('i18n.messages.copy', []));
});

test('the structured frontend catalogues expose the same translation keys', function () {
    $french = require lang_path('fr/frontend.php');
    $english = require lang_path('en/frontend.php');

    $keys = static function (array $messages): array {
        unset($messages['copy'], $messages['onboarding']['demo_profiles']);

        $flatten = function (array $items, string $prefix = '') use (&$flatten): array {
            $flattened = [];

            foreach ($items as $key => $value) {
                $path = $prefix === '' ? $key : "{$prefix}.{$key}";

                if (is_array($value)) {
                    $flattened = [...$flattened, ...$flatten($value, $path)];
                } else {
                    $flattened[] = $path;
                }
            }

            return $flattened;
        };

        $flattened = $flatten($messages);

        sort($flattened);

        return $flattened;
    };

    expect($keys($english))->toBe($keys($french));
});

test('the legacy English copy catalogue contains no empty translations', function () {
    $copy = require lang_path('en/frontend.php');

    expect($copy['copy'])
        ->not->toBeEmpty()
        ->each->toBeString()->not->toBeEmpty();
});

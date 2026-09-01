<?php

test('the matching entry point redirects to the localized public page', function (string $language, string $path) {
    $this->withHeader('Accept-Language', $language)
        ->get('/matching')
        ->assertRedirect($path);
})->with([
    'French' => ['fr-FR,fr;q=0.9,en;q=0.8', '/fr/matching'],
    'English' => ['en-GB,en;q=0.9,fr;q=0.8', '/en/matching'],
]);

test('localized matching pages are public server rendered and explain the current rules', function (
    string $path,
    string $locale,
    string $heading,
    string $interests,
    string $frequency,
    string $age,
    string $reciprocity,
) {
    $this->get($path)
        ->assertOk()
        ->assertViewIs('matching.show')
        ->assertHeaderMissing('X-Robots-Tag')
        ->assertSee('<html lang="'.$locale.'">', false)
        ->assertSee('<main', false)
        ->assertSee($heading)
        ->assertSee($interests)
        ->assertSee($frequency)
        ->assertSee($age)
        ->assertSee($reciprocity)
        ->assertDontSee('type="module"', false);
})->with([
    'French' => ['/fr/matching', 'fr', 'Comment fonctionnent les suggestions', 'Le nombre d’univers favoris actifs en commun passe en premier.', '0,25', 'L’âge sert uniquement à vérifier la majorité', 'deux Découvertes réciproques'],
    'English' => ['/en/matching', 'en', 'How suggestions work', 'The number of shared active favorite worlds comes first.', '0.25', 'Age is used only to verify adulthood', 'two mutual Discoveries'],
]);

test('localized matching pages expose canonical alternate and social metadata', function (string $path, string $locale, string $title) {
    config()->set('app.url', 'https://dlp-friends.example');

    $this->get($path)
        ->assertOk()
        ->assertSee('<title>'.$title.'</title>', false)
        ->assertSee('<link rel="canonical" href="https://dlp-friends.example/'.$locale.'/matching">', false)
        ->assertSee('<link rel="alternate" hreflang="fr" href="https://dlp-friends.example/fr/matching">', false)
        ->assertSee('<link rel="alternate" hreflang="en" href="https://dlp-friends.example/en/matching">', false)
        ->assertSee('<link rel="alternate" hreflang="x-default" href="https://dlp-friends.example/fr/matching">', false)
        ->assertSee('<meta property="og:type" content="article">', false)
        ->assertSee('<meta property="og:title" content="'.$title.'">', false)
        ->assertSee('<meta name="twitter:title" content="'.$title.'">', false)
        ->assertSee('<script type="application/ld+json">', false);
})->with([
    'French' => ['/fr/matching', 'fr', 'Comment fonctionne le matching de DLP Friends'],
    'English' => ['/en/matching', 'en', 'How DLP Friends matching works'],
]);

test('matching explanations cover every eligibility exclusion and ranking rule', function (string $path, array $rules) {
    $response = $this->get($path)->assertOk();

    foreach ($rules as $rule) {
        $response->assertSee($rule);
    }
})->with([
    'French' => ['/fr/matching', [
        'Le compte doit être actif et appartenir à une personne majeure.',
        'Le profil doit être complet, visible et utiliser un avatar encore actif.',
        'Ton propre profil et les profils que tu as déjà évalués sont exclus.',
        'Une personne que tu as bloquée, ou qui t’a bloqué, ne peut pas apparaître.',
        'À nombre égal, un bonus de 0,25 s’applique lorsque vous avez renseigné la même fréquence de visite.',
        'Si ces deux critères sont encore identiques, un départage aléatoire détermine l’ordre.',
    ]],
    'English' => ['/en/matching', [
        'The account must be active and belong to an adult.',
        'The profile must be complete, visible, and use an avatar that is still active.',
        'Your own profile and profiles you have already reviewed are excluded.',
        'Someone you blocked, or who blocked you, cannot appear.',
        'When that number is tied, a 0.25 bonus applies if you both entered the same visit frequency.',
        'If both criteria are still identical, a random tie-break determines the order.',
    ]],
]);

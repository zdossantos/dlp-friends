<?php

use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rules\Password;

test('registration validation is translated into the active locale', function (string $language, string $message) {
    $this->withHeader('Accept-Language', $language)
        ->post('/register', [
            'email' => '',
            'birth_date' => '',
            'password' => '',
            'password_confirmation' => '',
        ])
        ->assertSessionHasErrors(['email' => $message]);
})->with([
    ['fr-FR,fr;q=0.9', 'L’adresse e-mail est obligatoire.'],
    ['en-US,en;q=0.9', 'The email address field is required.'],
]);

test('the adult-only registration message is translated into the active locale', function (string $language, string $message) {
    $this->withHeader('Accept-Language', $language)
        ->post('/register', [
            'email' => 'minor@example.test',
            'birth_date' => today()->subYears(17)->toDateString(),
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertSessionHasErrors(['birth_date' => $message]);
})->with([
    ['fr-FR,fr;q=0.9', 'Tu dois être majeur pour t’inscrire.'],
    ['en-US,en;q=0.9', 'You must be an adult to register.'],
]);

test('social provider labels exist in every supported locale', function (string $locale) {
    $this->assertSame('Google', trans('account.social.google', locale: $locale));
})->with(['fr', 'en']);

test('validation catalogs cover every rule currently emitted by the application', function (string $locale) {
    $rules = [
        'accepted', 'array', 'before_or_equal', 'boolean', 'confirmed',
        'current_password', 'date', 'different', 'dimensions', 'distinct', 'email',
        'enum', 'exists', 'extensions', 'file', 'image', 'in', 'integer',
        'max.array', 'max.file', 'max.numeric', 'max.string',
        'min.array', 'min.file', 'min.numeric', 'min.string',
        'mimes', 'not_regex', 'password.letters', 'password.mixed', 'password.numbers',
        'password.symbols', 'password.uncompromised', 'present', 'regex',
        'required', 'string', 'unique', 'uploaded',
    ];
    $catalog = Arr::dot(require lang_path("{$locale}/validation.php"));

    expect(array_diff($rules, array_keys($catalog)))->toBe([]);

    foreach ($rules as $rule) {
        expect(trans("validation.{$rule}", [
            'attribute' => 'field',
            'date' => 'date',
            'max' => '10',
            'min' => '1',
            'other' => 'other field',
            'values' => 'value',
        ], $locale))->not->toContain('validation.')->not->toMatch('/:[a-z_]+/');
    }
})->with(['fr', 'en']);

test('a compromised registration password has a localized validation message', function (string $language, string $message) {
    $this->app->instance(UncompromisedVerifier::class, new class implements UncompromisedVerifier
    {
        public function verify($data)
        {
            return false;
        }
    });
    Password::defaults(fn () => Password::min(8)->uncompromised());

    try {
        $this->withHeader('Accept-Language', $language)
            ->post('/register', [
                'email' => 'member@example.test',
                'birth_date' => '2000-01-01',
                'password' => 'compromised-password',
                'password_confirmation' => 'compromised-password',
                'terms_accepted' => true,
            ])
            ->assertSessionHasErrors(['password' => $message]);
    } finally {
        Password::defaults(fn () => Password::min(8));
    }
})->with([
    ['fr-FR,fr;q=0.9', 'Le mot de passe fourni est apparu dans une fuite de données. Choisis-en un autre.'],
    ['en-US,en;q=0.9', 'The given password has appeared in a data leak. Please choose a different password.'],
]);

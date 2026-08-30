<?php

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

<?php

use App\Mail\ResetPasswordMail;
use App\Mail\VerifyEmailMail;

test('the verification email renders the french content and accessible fallback link', function () {
    $url = 'https://dlp-friends.test/email/verify/42/example-signature';
    $mail = (new VerifyEmailMail($url))->locale('fr');

    $mail->assertHasSubject('Vérifiez votre adresse e-mail');
    foreach ([
        'DLP Friends',
        'Bienvenue dans la communauté',
        'Vérifier mon adresse e-mail',
        'Copiez et collez ce lien dans votre navigateur',
        'indépendant et non affilié à Disney ou Disneyland Paris',
        $url,
    ] as $text) {
        $mail->assertSeeInHtml($text);
    }

    foreach ([
        'Vérifier mon adresse e-mail',
        'Copiez et collez ce lien dans votre navigateur',
        $url,
    ] as $text) {
        $mail->assertSeeInText($text);
    }
});

test('the verification email renders the english content', function () {
    $mail = (new VerifyEmailMail('https://dlp-friends.test/email/verify/42/signature'))->locale('en');

    $mail->assertHasSubject('Verify your email address');
    foreach ([
        'Welcome to the community',
        'Verify my email address',
        'Copy and paste this link into your browser',
        'is an independent service and not affiliated with Disney or Disneyland Paris',
    ] as $text) {
        $mail->assertSeeInHtml($text);
    }
});

test('the password reset email renders the french content and expiry', function () {
    config()->set('auth.passwords.users.expire', 60);
    $url = 'https://dlp-friends.test/reset-password/example-token?email=membre%40example.test';
    $mail = (new ResetPasswordMail('example-token', $url))->locale('fr');

    $mail->assertHasSubject('Réinitialisez votre mot de passe');
    foreach ([
        'Réinitialisation du mot de passe',
        'Réinitialiser mon mot de passe',
        'Ce lien expirera dans 60 minutes.',
        'Copiez et collez ce lien dans votre navigateur',
        $url,
    ] as $text) {
        $mail->assertSeeInHtml($text);
    }

    foreach ([
        'Réinitialiser mon mot de passe',
        'Ce lien expirera dans 60 minutes.',
        $url,
    ] as $text) {
        $mail->assertSeeInText($text);
    }
});

test('the password reset email renders the english content and expiry', function () {
    config()->set('auth.passwords.users.expire', 60);
    $mail = (new ResetPasswordMail(
        'example-token',
        'https://dlp-friends.test/reset-password/example-token?email=member%40example.test',
    ))->locale('en');

    $mail->assertHasSubject('Reset your password');
    foreach ([
        'Password reset',
        'Reset my password',
        'This link will expire in 60 minutes.',
        'Copy and paste this link into your browser',
    ] as $text) {
        $mail->assertSeeInHtml($text);
    }
});

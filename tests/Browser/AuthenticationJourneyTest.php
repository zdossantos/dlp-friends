<?php

use App\Mail\ResetPasswordMail;
use App\Mail\VerifyEmailMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PragmaRX\Google2FA\Google2FA;

test('a guest can retry invalid credentials then log in and lose private access on logout', function (int $width) {
    $user = User::factory()->withProfile()->create(['locale' => 'fr']);

    $page = visit('/login', ['locale' => 'fr-FR'])->resize($width, 900)
        ->fill('#email', $user->email)
        ->fill('#password', 'incorrect-password')
        ->click('[data-test="login-button"]')
        ->assertSee(__('auth.failed'))
        ->assertPathIs('/login')
        ->fill('#password', 'password')
        ->click('[data-test="login-button"]')
        ->assertPathIs('/discover')
        ->navigate('/profile')
        ->click('[aria-label="Se déconnecter"]')
        ->assertPathIs('/fr')
        ->navigate('/settings/account')
        ->assertPathIs('/login')
        ->assertNoJavaScriptErrors();

    $this->assertGuest();
})->with([320, 1440]);

test('two factor login accepts an authenticator code before granting private access', function (int $width) {
    $authenticator = new Google2FA;
    $secret = $authenticator->generateSecretKey();
    $user = User::factory()->withProfile()->withTwoFactor()->create([
        'locale' => 'fr',
        'two_factor_secret' => encrypt($secret),
    ]);

    visit('/login', ['locale' => 'fr-FR'])->resize($width, 900)
        ->fill('#email', $user->email)
        ->fill('#password', 'password')
        ->click('[data-test="login-button"]')
        ->assertPathIs('/two-factor-challenge')
        ->fill('#otp', $authenticator->getCurrentOtp($secret))
        ->click('button[type="submit"]')
        ->assertPathIs('/discover')
        ->assertNoJavaScriptErrors();
})->with([320, 1440]);

test('a two factor recovery code grants access once and cannot be reused', function (int $width) {
    $user = User::factory()->withProfile()->withTwoFactor()->create(['locale' => 'fr']);

    $page = visit('/login', ['locale' => 'fr-FR'])->resize($width, 900)
        ->fill('#email', $user->email)
        ->fill('#password', 'password')
        ->click('[data-test="login-button"]')
        ->assertPathIs('/two-factor-challenge')
        ->click('utiliser un code de récupération')
        ->fill('[name="recovery_code"]', 'recovery-code-1')
        ->press('Continuer')
        ->assertPathIs('/discover');

    expect($user->fresh()->recoveryCodes())->not->toContain('recovery-code-1');

    $page->navigate('/profile')
        ->click('[aria-label="Se déconnecter"]')
        ->assertPathIs('/fr')
        ->navigate('/login')
        ->fill('#email', $user->email)
        ->fill('#password', 'password')
        ->click('[data-test="login-button"]')
        ->assertPathIs('/two-factor-challenge')
        ->click('utiliser un code de récupération')
        ->fill('[name="recovery_code"]', 'recovery-code-1')
        ->press('Continuer')
        ->assertSee(__('The provided two factor recovery code was invalid.'))
        ->navigate('/discover')
        ->assertPathIs('/login')
        ->assertNoJavaScriptErrors();
})->with([320, 1440]);

test('registration follows the actual verification email link before profile creation', function (int $width) {
    Mail::fake();

    $page = visit('/register', ['locale' => 'fr-FR'])->resize($width, 900)
        ->fill('#email', 'verified-journey@example.com')
        ->fill('#birth_date', '2000-01-01')
        ->fill('#password', 'password')
        ->fill('#password_confirmation', 'password')
        ->click('#terms_accepted')
        ->click('[data-test="register-user-button"]')
        ->assertPathIs('/email/verify');

    $user = User::query()->where('email', 'verified-journey@example.com')->sole();
    expect($user->hasVerifiedEmail())->toBeFalse();
    $mail = Mail::sent(VerifyEmailMail::class)->sole();

    $page->navigate($mail->verificationUrl)
        ->assertPathIs('/profile/create')
        ->assertNoJavaScriptErrors();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
})->with([320, 1440]);

test('password recovery follows the sent link and the used token cannot reset it again', function (int $width) {
    Mail::fake();
    $user = User::factory()->withProfile()->create(['locale' => 'fr']);

    $page = visit('/login', ['locale' => 'fr-FR'])->resize($width, 900)
        ->click('a[href$="/forgot-password"]')
        ->assertPathIs('/forgot-password')
        ->fill('#email', $user->email)
        ->click('[data-test="email-password-reset-link-button"]')
        ->assertSee(__('passwords.sent'));

    $mail = Mail::sent(ResetPasswordMail::class)->sole();
    $page->navigate($mail->resetUrl)
        ->assertValue('#email', $user->email)
        ->fill('#password', 'new-password-journey')
        ->fill('#password_confirmation', 'new-password-journey')
        ->click('[data-test="reset-password-button"]')
        ->assertPathIs('/login');

    expect(Hash::check('new-password-journey', $user->fresh()->password))->toBeTrue();

    $page->navigate($mail->resetUrl)
        ->fill('#password', 'reused-token-password')
        ->fill('#password_confirmation', 'reused-token-password')
        ->click('[data-test="reset-password-button"]')
        ->assertSee(__('passwords.token'))
        ->navigate('/login')
        ->fill('#email', $user->email)
        ->fill('#password', 'new-password-journey')
        ->click('[data-test="login-button"]')
        ->assertPathIs('/discover')
        ->assertNoJavaScriptErrors();

    expect(Hash::check('new-password-journey', $user->fresh()->password))->toBeTrue();
})->with([320, 1440]);

test('a member enrolls confirms regenerates recovery codes and disables two factor authentication', function (int $width) {
    $user = User::factory()->withProfile()->create();
    $this->actingAs($user);
    $page = visit('/settings/security')->resize($width, 900)
        ->fill('#password', 'password')->click('@confirm-password-button')
        ->click('Activer la double authentification')->assertSee('ou saisis la clé manuellement');
    expect($user->fresh()->two_factor_secret)->not->toBeNull()
        ->and($user->fresh()->two_factor_confirmed_at)->toBeNull();
    $secret = decrypt($user->fresh()->two_factor_secret);
    $page->click('Continuer')->fill('#otp', (new Google2FA)->getCurrentOtp($secret))
        ->click('Confirmer')->assertSee('Désactiver la double authentification');
    expect($user->fresh()->two_factor_confirmed_at)->not->toBeNull();
    $originalCodes = $user->fresh()->recoveryCodes();
    $page->click('Afficher les codes')->assertSee($originalCodes[0])->click('Régénérer les codes')
        ->assertDontSee($originalCodes[0]);
    $newCodes = $user->fresh()->recoveryCodes();
    expect($newCodes)->toHaveCount(8)->not->toBe($originalCodes);
    $page->assertSee($newCodes[0])->click('Masquer les codes')
        ->click('Désactiver la double authentification')->assertSee('Activer la double authentification')
        ->assertNoJavaScriptErrors();
    expect($user->fresh()->two_factor_secret)->toBeNull()
        ->and($user->fresh()->two_factor_recovery_codes)->toBeNull()
        ->and($user->fresh()->two_factor_confirmed_at)->toBeNull();
})->with([320, 1440]);

test('registration refuses a minor then accepts a corrected adult birth date', function () {
    Mail::fake();
    $page = visit('/register', ['locale' => 'fr-FR'])->resize(320, 800)
        ->fill('#email', 'age-boundary@example.com')->fill('#birth_date', today()->subYears(17)->format('Y-m-d'))
        ->fill('#password', 'password')->fill('#password_confirmation', 'password')->click('#terms_accepted');
    // Bypass the HTML date boundary to exercise the real server-side age rule.
    $page->script("document.querySelector('#birth_date').removeAttribute('max')");
    $page->click('@register-user-button')->assertSee('Tu dois être majeur pour t’inscrire.');
    expect(User::query()->where('email', 'age-boundary@example.com')->exists())->toBeFalse();
    $page->fill('#birth_date', today()->subYears(18)->format('Y-m-d'))
        ->fill('#password', 'password')->fill('#password_confirmation', 'password')
        ->click('@register-user-button')->assertPathIs('/email/verify')->assertNoJavaScriptErrors();
    expect(User::query()->where('email', 'age-boundary@example.com')->sole()->hasVerifiedEmail())->toBeFalse();
});

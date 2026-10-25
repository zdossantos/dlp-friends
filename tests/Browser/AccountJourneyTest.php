<?php

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('account updates reject a duplicate email preserve presence and require reverification after an email change', function (int $width) {
    $user = User::factory()->withProfile()->create(['show_presence' => true]);
    $other = User::factory()->create();
    $this->actingAs($user);

    $page = visit('/settings/account', ['locale' => 'fr-FR'])->resize($width, 900)
        ->fill('#email', $other->email)
        ->click('[data-test="update-account-button"]')
        ->assertSee(__('validation.unique', ['attribute' => __('validation.attributes.email')]))
        ->assertPathIs('/settings/account');

    expect($user->fresh()->email)->toBe($user->email);

    $page->fill('#email', $user->email)
        ->click('[role="switch"]')
        ->click('[data-test="update-account-button"]')
        ->assertSee('Compte mis à jour.')
        ->navigate('/settings/account')
        ->assertAttribute('[role="switch"]', 'aria-checked', 'false');

    expect($user->fresh()->show_presence)->toBeFalse();

    $page->fill('#email', 'changed-journey@example.com')
        ->click('[data-test="update-account-button"]')
        ->assertPathIs('/email/verify')
        ->navigate('/discover')
        ->assertPathIs('/email/verify')
        ->assertNoJavaScriptErrors();

    expect($user->fresh()->email)->toBe('changed-journey@example.com')
        ->and($user->fresh()->email_verified_at)->toBeNull();
})->with([320, 1440]);

test('a member confirms security access changes their password and signs in with the new password', function (int $width) {
    $user = User::factory()->withProfile()->create();
    $this->actingAs($user);

    $page = visit('/settings/security', ['locale' => 'fr-FR'])->resize($width, 900)
        ->assertPathIs('/user/confirm-password')
        ->fill('#password', 'password')
        ->click('[data-test="confirm-password-button"]')
        ->assertPathIs('/settings/security')
        ->fill('#current_password', 'wrong-password')
        ->fill('#password', 'changed-security-password')
        ->fill('#password_confirmation', 'changed-security-password')
        ->click('[data-test="update-password-button"]')
        ->assertSee(__('validation.current_password'));

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();

    $page->fill('#current_password', 'password')
        ->fill('#password', 'changed-security-password')
        ->fill('#password_confirmation', 'changed-security-password')
        ->click('[data-test="update-password-button"]')
        ->assertSee(__('Password updated.'))
        ->navigate('/profile')
        ->click('[aria-label="Se déconnecter"]')
        ->assertPathIs('/fr')
        ->navigate('/login')
        ->fill('#email', $user->email)
        ->fill('#password', 'changed-security-password')
        ->click('[data-test="login-button"]')
        ->assertPathIs('/discover')
        ->assertNoJavaScriptErrors();
})->with([320, 1440]);

test('password account deletion rejects an incorrect password then revokes private access', function (int $width) {
    $user = User::factory()->withProfile()->create();
    $this->actingAs($user);

    $page = visit('/settings/account', ['locale' => 'fr-FR'])->resize($width, 900)
        ->click('[data-test="delete-user-button"]')
        ->fill('#password', 'wrong-password')
        ->click('[data-test="confirm-delete-user-button"]')
        ->assertSee(__('validation.current_password'));

    expect($user->fresh()->status)->toBe(UserStatus::Active);

    $page->fill('#password', 'password')
        ->click('[data-test="confirm-delete-user-button"]')
        ->assertPathIsNot('/settings/account')
        ->navigate('/settings/account')
        ->assertPathIs('/login')
        ->assertNoJavaScriptErrors();

    expect($user->fresh()->status)->toBe(UserStatus::PendingDeletion)
        ->and($user->fresh()->deletion_requested_at)->not->toBeNull();
})->with([320, 1440]);

test('the export button downloads the authenticated members JSON without another accounts data', function (int $width) {
    $user = User::factory()->withProfile()->create();
    User::factory()->withProfile()->create(['email' => 'private-other@example.com']);
    $this->actingAs($user);

    $page = visit('/settings/account', ['locale' => 'fr-FR'])->resize($width, 900);
    // Observe the real response Blob without replacing fetch or the download.
    $page->script('const create = URL.createObjectURL.bind(URL); URL.createObjectURL = (blob) => { blob.text().then(text => { window.exportText = text; }); return create(blob); }; void 0;');
    $page->click('[data-test="download-data-export"]')
        ->assertScript('typeof window.exportText', 'string')
        ->assertScript('JSON.parse(window.exportText).account.email', $user->email)
        ->assertScript('window.exportText.includes("private-other@example.com")', false)
        ->assertScript('Object.hasOwn(JSON.parse(window.exportText).account, "password")', false)
        ->assertEnabled('[data-test="download-data-export"]')
        ->assertNoJavaScriptErrors();
})->with([320, 1440]);

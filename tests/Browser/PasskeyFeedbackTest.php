<?php

use App\Models\User;

test('passkey registration translates the suggested name and browser failures', function (string $locale, string $errorName, string $translation) {
    $this->actingAs(User::factory()->withProfile()->create(['locale' => $locale]))
        ->withSession(['auth.password_confirmed_at' => time()]);

    $page = visit('/settings/appearance');
    $page->script("Object.defineProperty(navigator, 'userAgent', { configurable: true, value: 'Mozilla/5.0 (Macintosh) Chrome/130.0' }); navigator.credentials.create = async () => { throw new DOMException('Technical browser detail', '{$errorName}'); }; void 0;");
    $page->click('nav a[href$="/security"]')
        ->press(__('account.passkeys.add', [], $locale))
        ->assertValue('#passkey-name', $locale === 'fr' ? 'Chrome sur Mac' : 'Chrome on Mac')
        ->press(__('account.passkeys.save', [], $locale))
        ->assertSee(__($translation, [], $locale))
        ->assertDontSee('Technical browser detail')
        ->fill('#passkey-name', 'Ma clé personnelle')
        ->press(__('account.passkeys.save', [], $locale))
        ->assertSee(__($translation, [], $locale))
        ->assertValue('#passkey-name', 'Ma clé personnelle')
        ->assertNoJavaScriptErrors();
})->with([
    'French cancellation' => ['fr', 'NotAllowedError', 'account.passkeys.cancelled'],
    'English cancellation' => ['en', 'NotAllowedError', 'account.passkeys.cancelled'],
    'French technical error' => ['fr', 'UnknownError', 'account.passkeys.failed'],
    'English technical error' => ['en', 'UnknownError', 'account.passkeys.failed'],
]);

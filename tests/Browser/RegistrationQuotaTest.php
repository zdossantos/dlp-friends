<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    config()->set('mail.default', 'resend');
    config()->set('services.resend.usage_key', 'browser-usage-key');
    Mail::fake();
    Http::preventStrayRequests();
});

test('registration hides email fields at the quota limit and keeps Google accessible', function (string $locale, string $message, int $used) {
    Http::fake(['api.resend.com/usage' => Http::response(['emails' => [
        'daily' => ['used' => $used, 'limit' => 100, 'resets_at' => now()->addDay()->toIso8601String()],
        'monthly' => ['used' => 100, 'limit' => 3000, 'resets_at' => now()->addMonth()->toIso8601String()],
    ]])]);

    $page = visit('/register', ['locale' => $locale])->resize(390, 844)
        ->assertSeeLink('Google')
        ->assertAttribute('[data-test="social-google"]', 'href', '/auth/google/redirect');

    if ($used < 100) {
        $page->assertPresent('input[name="email"]')
            ->assertPresent('[data-test="register-user-button"]')
            ->assertMissing('[data-test="email-registration-unavailable"]');
    } else {
        $page->assertSee($message)
            ->assertMissing('input[name="email"]')
            ->assertMissing('input[name="password"]')
            ->assertMissing('[data-test="register-user-button"]')
            ->keys('[data-test="social-google"]', 'Tab')
            ->assertScript('document.activeElement.tagName === "A"', true);
    }

    $page->assertNoAccessibilityIssues()->assertNoJavaScriptErrors();
})->with([
    ['fr-FR', 'L’inscription par e-mail est temporairement indisponible. Tu peux continuer avec Google.', 100],
    ['en-GB', 'Email registration is temporarily unavailable. You can continue with Google.', 100],
    ['fr-FR', '', 99],
]);

test('an open registration form is refused when the quota becomes exhausted', function () {
    $quota = fn (int $used): array => ['emails' => [
        'daily' => ['used' => $used, 'limit' => 100, 'resets_at' => now()->addDay()->toIso8601String()],
        'monthly' => ['used' => 100, 'limit' => 3000, 'resets_at' => now()->addMonth()->toIso8601String()],
    ]];
    Http::fake(['api.resend.com/usage' => Http::sequence()->push($quota(99))->push($quota(100))]);

    visit('/register', ['locale' => 'fr-FR'])
        ->fill('email', 'stale-form@example.com')
        ->fill('birth_date', '2000-01-01')
        ->fill('password', 'password')
        ->fill('password_confirmation', 'password')
        ->click('#terms_accepted')
        ->click('[data-test="register-user-button"]')
        ->assertSee('L’inscription par e-mail est temporairement indisponible. Tu peux continuer avec Google.')
        ->assertMissing('[data-test="register-user-button"]')
        ->assertSeeLink('Google')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseMissing('users', ['email' => 'stale-form@example.com']);
    Mail::assertNothingOutgoing();
});

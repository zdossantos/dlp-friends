<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('mail.default', 'resend');
    config()->set('services.resend.usage_key', 'test-usage-key');
    Mail::fake();
    Http::preventStrayRequests();
});

function registrationUsage(int $daily = 99, int $monthly = 2999): array
{
    return ['emails' => [
        'daily' => ['used' => $daily, 'limit' => 100, 'resets_at' => now()->addDay()->toIso8601String()],
        'monthly' => ['used' => $monthly, 'limit' => 3000, 'resets_at' => now()->addMonth()->toIso8601String()],
    ]];
}

function quotaRegistrationInput(): array
{
    return [
        'email' => 'quota@example.com', 'birth_date' => '2000-01-01',
        'password' => 'password', 'password_confirmation' => 'password', 'terms_accepted' => true,
    ];
}

test('registration is available below both Resend limits', function () {
    Http::fake(['api.resend.com/usage' => Http::response(registrationUsage())]);

    $this->get('/register')->assertInertia(fn (Assert $page) => $page->where('emailRegistrationAvailable', true));
    $this->post('/register', quotaRegistrationInput())->assertRedirect('/app');
    $this->assertAuthenticated();
    expect(User::where('email', 'quota@example.com')->exists())->toBeTrue();
    Http::assertSent(fn ($request) => $request->url() === 'https://api.resend.com/usage'
        && $request->hasHeader('Authorization', 'Bearer test-usage-key'));
});

test('exhausted quota refuses registration without creating an account or sending verification', function (int $daily, int $monthly) {
    Http::fake(['api.resend.com/usage' => Http::response(registrationUsage($daily, $monthly))]);

    $this->get('/register')->assertInertia(fn (Assert $page) => $page->where('emailRegistrationAvailable', false));
    $this->from('/register')->post('/register', quotaRegistrationInput())
        ->assertRedirect('/register')->assertSessionHasErrors('email');

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'quota@example.com']);
    Mail::assertNothingOutgoing();
})->with([[100, 2999], [99, 3000], [101, 3001]]);

test('a form opened before exhaustion cannot bypass the fresh server check', function () {
    Http::fake(['api.resend.com/usage' => Http::sequence()
        ->push(registrationUsage())->push(registrationUsage(100))]);

    $this->get('/register')->assertInertia(fn (Assert $page) => $page->where('emailRegistrationAvailable', true));
    $this->post('/register', quotaRegistrationInput())->assertSessionHasErrors('email');
    $this->assertDatabaseMissing('users', ['email' => 'quota@example.com']);
    Mail::assertNothingOutgoing();
    $this->get('/register')->assertInertia(fn (Assert $page) => $page->where('emailRegistrationAvailable', false));
    Http::assertSentCount(2);
});

test('unverifiable quota closes email registration', function (string $failure) {
    $usage = registrationUsage();
    if ($failure === 'stale') {
        $usage['emails']['daily']['resets_at'] = now()->subSecond()->toIso8601String();
    }
    if ($failure === 'malformed') {
        unset($usage['emails']['monthly']['limit']);
    }
    Http::fake(['api.resend.com/usage' => match ($failure) {
        'connection' => Http::failedConnection(),
        'forbidden' => Http::response([], 403),
        'rate limited' => Http::response([], 429),
        'unavailable' => Http::response([], 503),
        default => Http::response($usage),
    }]);

    $this->post('/register', quotaRegistrationInput())->assertSessionHasErrors('email');
    $this->assertDatabaseMissing('users', ['email' => 'quota@example.com']);
    Mail::assertNothingOutgoing();
})->with(['connection', 'forbidden', 'rate limited', 'unavailable', 'malformed', 'stale']);

test('page state expires after sixty seconds and reopens after a successful check', function () {
    Http::fake(['api.resend.com/usage' => Http::sequence()->push(registrationUsage(100))->push(registrationUsage())]);
    $this->get('/register')->assertInertia(fn (Assert $page) => $page->where('emailRegistrationAvailable', false));
    $this->get('/register')->assertInertia(fn (Assert $page) => $page->where('emailRegistrationAvailable', false));
    Http::assertSentCount(1);
    $this->travel(61)->seconds();
    $this->get('/register')->assertInertia(fn (Assert $page) => $page->where('emailRegistrationAvailable', true));
    Http::assertSentCount(2);
});

test('unlimited email quotas allow registration', function () {
    $usage = registrationUsage();
    $usage['emails']['daily']['limit'] = null;
    $usage['emails']['monthly']['limit'] = null;
    Http::fake(['api.resend.com/usage' => Http::response($usage)]);
    $this->post('/register', quotaRegistrationInput())->assertRedirect('/app');
});

test('missing usage credentials suspend email registration without contacting Resend', function () {
    config()->set('services.resend.usage_key', null);
    $this->post('/register', quotaRegistrationInput())->assertSessionHasErrors('email');
    Http::assertNothingSent();
    Mail::assertNothingOutgoing();
});

test('local mail transport does not require Resend', function () {
    config()->set('mail.default', 'array');
    $this->post('/register', quotaRegistrationInput())->assertRedirect('/app');
    Http::assertNothingSent();
});

test('Google registration remains functional while email registration is suspended', function () {
    Http::fake(['api.resend.com/usage' => Http::response(registrationUsage(100))]);
    $this->get('/register')->assertInertia(fn (Assert $page) => $page->where('emailRegistrationAvailable', false));

    Socialite::fake('google',
        Laravel\Socialite\Two\User::fake(['id' => 'quota-google', 'email' => 'google-quota@example.com'])
            ->setRaw(['verified_email' => true]));
    $this->get('/auth/google/redirect')->assertRedirect('https://socialite.fake/google/authorize');
    $this->get('/auth/google/callback')->assertRedirect(route('social.registration.create'));
    $this->post(route('social.registration.store'), [
        'birth_date' => '2000-01-01', 'terms_accepted' => true,
    ])->assertRedirect('/app');

    $this->assertAuthenticated();
    expect(User::where('email', 'google-quota@example.com')->firstOrFail()->hasVerifiedEmail())->toBeTrue();
    Mail::assertNothingOutgoing();
    Http::assertSentCount(1);
});

test('an unavailable provider is retried and registration recovers', function () {
    Http::fake(['api.resend.com/usage' => Http::sequence()->pushStatus(503)->push(registrationUsage())]);
    $this->get('/register')->assertInertia(fn (Assert $page) => $page->where('emailRegistrationAvailable', false));
    $this->travel(61)->seconds();
    $this->get('/register')->assertInertia(fn (Assert $page) => $page->where('emailRegistrationAvailable', true));
});

test('cached quota is discarded when its period ends before sixty seconds', function () {
    $usage = registrationUsage(100);
    $usage['emails']['daily']['resets_at'] = now()->addSeconds(10)->toIso8601String();
    Http::fake(['api.resend.com/usage' => Http::sequence()->push($usage)->push(registrationUsage())]);
    $this->get('/register')->assertInertia(fn (Assert $page) => $page->where('emailRegistrationAvailable', false));
    $this->travel(11)->seconds();
    $this->get('/register')->assertInertia(fn (Assert $page) => $page->where('emailRegistrationAvailable', true));
    Http::assertSentCount(2);
});

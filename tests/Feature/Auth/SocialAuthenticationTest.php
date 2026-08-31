<?php

namespace Tests\Feature\Auth;

use App\Actions\CreateSocialUser;
use App\Data\PendingSocialIdentity;
use App\Enums\RoleName;
use App\Enums\SocialProvider;
use App\Enums\UserStatus;
use App\Exceptions\SocialAuthenticationException;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SocialAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @return array<string, array{string}> */
    public static function providers(): array
    {
        return [
            'Google' => ['google'],
            'Apple' => ['apple'],
        ];
    }

    #[DataProvider('providers')]
    public function test_visitors_can_start_authentication_with_an_allowed_provider(string $provider): void
    {
        Socialite::fake($provider);

        $response = $this->get(route('social.redirect', $provider));

        $response->assertRedirect("https://socialite.fake/{$provider}/authorize");
    }

    public function test_unknown_providers_are_rejected(): void
    {
        $this->get('/auth/unknown/redirect')->assertNotFound();
    }

    #[DataProvider('providers')]
    public function test_new_verified_identity_is_kept_token_free_until_registration(string $provider): void
    {
        Socialite::fake($provider, $this->socialiteUser($provider, email: ' New@Example.COM '));

        $response = $this->performCallback($provider);

        $response->assertRedirect(route('social.registration.create'));
        $response->assertSessionHas(PendingSocialIdentity::SESSION_KEY, [
            'provider' => $provider,
            'provider_user_id' => "{$provider}-123",
            'email' => 'new@example.com',
        ]);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_google_callback_accepts_get_and_apple_callback_accepts_only_post(): void
    {
        Socialite::fake('google', $this->socialiteUser('google'));
        $this->get('/auth/google/callback')->assertRedirect(route('social.registration.create'));

        Socialite::fake('apple', $this->socialiteUser('apple'));
        $this->post('/auth/apple/callback')->assertRedirect(route('social.registration.create'));
        $this->get('/auth/apple/callback')->assertMethodNotAllowed();
        $this->post('/auth/google/callback')->assertMethodNotAllowed();
    }

    #[DataProvider('providers')]
    public function test_existing_link_reconnects_the_active_user_without_rechecking_email(string $provider): void
    {
        $user = User::factory()->create();
        SocialAccount::factory()->for($user)->create([
            'provider' => $provider,
            'provider_user_id' => "{$provider}-123",
        ]);
        Socialite::fake($provider, SocialiteUser::fake([
            'id' => "{$provider}-123",
            'email' => null,
        ])->setRaw([]));

        $response = $this->performCallback($provider);

        $response->assertRedirect(route('app'));
        $this->assertAuthenticatedAs($user);
        $response->assertSessionMissing(PendingSocialIdentity::SESSION_KEY);
    }

    #[DataProvider('providers')]
    public function test_existing_email_is_never_automatically_linked(string $provider): void
    {
        User::factory()->create(['email' => 'existing@example.com']);
        Socialite::fake($provider, $this->socialiteUser($provider, email: 'existing@example.com'));

        $response = $this->performCallback($provider);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('social_auth');
        $this->assertGuest();
        $this->assertDatabaseCount('social_accounts', 0);
    }

    #[DataProvider('providers')]
    public function test_inactive_linked_user_cannot_reconnect(string $provider): void
    {
        $user = User::factory()->create(['status' => UserStatus::PendingDeletion]);
        SocialAccount::factory()->for($user)->create([
            'provider' => $provider,
            'provider_user_id' => "{$provider}-123",
        ]);
        Socialite::fake($provider, $this->socialiteUser($provider));

        $response = $this->performCallback($provider);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('social_auth');
        $this->assertGuest();
    }

    #[DataProvider('providers')]
    public function test_new_identity_requires_a_provider_verified_email(string $provider): void
    {
        Socialite::fake($provider, $this->socialiteUser($provider, verified: false));

        $response = $this->performCallback($provider);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('social_auth');
        $response->assertSessionMissing(PendingSocialIdentity::SESSION_KEY);
        $this->assertGuest();
    }

    #[DataProvider('providers')]
    public function test_new_identity_requires_an_id_and_email(string $provider): void
    {
        Socialite::fake($provider, $this->socialiteUser($provider, id: null));
        $this->performCallback($provider)
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('social_auth');

        Socialite::fake($provider, $this->socialiteUser($provider, email: null));
        $this->performCallback($provider)
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('social_auth');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    #[DataProvider('providers')]
    public function test_invalid_state_is_reported_without_exposing_provider_details(string $provider): void
    {
        Socialite::fake($provider, fn () => throw new InvalidStateException('sensitive provider payload'));

        $response = $this->performCallback($provider);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('social_auth');
        $this->assertStringNotContainsString(
            'sensitive provider payload',
            implode(' ', $response->getSession()->get('errors')->all()),
        );
        $this->assertGuest();
    }

    #[DataProvider('providers')]
    public function test_provider_cancellation_is_reported_safely(string $provider): void
    {
        Socialite::fake($provider, $this->socialiteUser($provider));

        $response = $provider === 'apple'
            ? $this->withSession(['state' => 'expected'])->post('/auth/apple/callback', ['error' => 'access_denied', 'state' => 'expected'])
            : $this->withSession(['state' => 'expected'])->get('/auth/google/callback?error=access_denied&state=expected');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('social_auth');
        $response->assertSessionMissing(PendingSocialIdentity::SESSION_KEY);
        $response->assertSessionMissing('state');
        $this->assertGuest();
    }

    #[DataProvider('providers')]
    public function test_provider_cancellation_requires_a_valid_oauth_state(string $provider): void
    {
        Socialite::fake($provider, $this->socialiteUser($provider));

        $response = $provider === 'apple'
            ? $this->withSession(['state' => 'expected'])->post('/auth/apple/callback', ['error' => 'access_denied', 'state' => 'forged'])
            : $this->withSession(['state' => 'expected'])->get('/auth/google/callback?error=access_denied&state=forged');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors([
            'social_auth' => __('social_auth.invalid_callback'),
        ]);
        $response->assertSessionMissing('state');
        $this->assertGuest();
    }

    #[DataProvider('providers')]
    public function test_expected_provider_failures_are_reported_safely(string $provider): void
    {
        Socialite::fake($provider, fn () => throw new \RuntimeException('sensitive provider response'));

        $response = $this->performCallback($provider);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors([
            'social_auth' => __('social_auth.unavailable'),
        ]);
        $this->assertStringNotContainsString(
            'sensitive provider response',
            implode(' ', $response->getSession()->get('errors')->all()),
        );
    }

    public function test_social_registration_form_requires_a_valid_pending_identity(): void
    {
        $this->get(route('social.registration.create'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('social_auth');

        $this->withSession([PendingSocialIdentity::SESSION_KEY => ['provider' => 'google']])
            ->get(route('social.registration.create'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('social_auth');
    }

    public function test_social_registration_form_does_not_expose_provider_identity(): void
    {
        $this->withSession([PendingSocialIdentity::SESSION_KEY => $this->pendingIdentity()])
            ->get(route('social.registration.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/CompleteSocialRegistration')
                ->missing('provider')
                ->missing('provider_user_id')
                ->missing('email')
                ->missing('token'));
    }

    public function test_social_registration_requires_an_adult_birth_date(): void
    {
        Carbon::setTestNow('2026-08-31');

        $this->withSession([PendingSocialIdentity::SESSION_KEY => $this->pendingIdentity()])
            ->from(route('social.registration.create'))
            ->post(route('social.registration.store'))
            ->assertRedirect(route('social.registration.create'))
            ->assertSessionHasErrors('birth_date');

        $this->withSession([PendingSocialIdentity::SESSION_KEY => $this->pendingIdentity()])
            ->from(route('social.registration.create'))
            ->post(route('social.registration.store'), ['birth_date' => '2008-09-01'])
            ->assertRedirect(route('social.registration.create'))
            ->assertSessionHasErrors([
                'birth_date' => 'Tu dois être majeur pour t’inscrire.',
            ]);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_social_user_action_enforces_adulthood_itself(): void
    {
        Carbon::setTestNow('2026-08-31');

        $identity = new PendingSocialIdentity(
            SocialProvider::Google,
            'google-123',
            'new@example.com',
        );

        try {
            app(CreateSocialUser::class)->execute($identity, '2008-09-01');
            $this->fail('An underage social account was created.');
        } catch (SocialAuthenticationException $exception) {
            $this->assertSame('social_auth.adult_required', $exception->translationKey());
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_adult_can_complete_social_registration(): void
    {
        Carbon::setTestNow('2026-08-31 12:00:00');

        $response = $this->withSession([
            PendingSocialIdentity::SESSION_KEY => $this->pendingIdentity(),
            'url.intended' => '/dashboard',
        ])->post(route('social.registration.store'), ['birth_date' => '2008-08-31']);

        $user = User::query()->where('email', 'new@example.com')->firstOrFail();
        $account = $user->socialAccounts()->sole();

        $response->assertRedirect(route('app'));
        $response->assertSessionMissing(PendingSocialIdentity::SESSION_KEY);
        $this->assertAuthenticatedAs($user);
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertFalse(Hash::needsRehash($user->getRawOriginal('password')));
        $this->assertTrue($user->load('roles')->hasRole(RoleName::User));
        $this->assertSame('google', $account->provider);
        $this->assertSame('google-123', $account->provider_user_id);
    }

    public function test_completion_email_conflict_rolls_back_and_expires_the_flow(): void
    {
        User::factory()->create(['email' => 'new@example.com']);

        $response = $this->withSession([PendingSocialIdentity::SESSION_KEY => $this->pendingIdentity()])
            ->post(route('social.registration.store'), ['birth_date' => '2000-01-01']);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('social_auth');
        $response->assertSessionMissing(PendingSocialIdentity::SESSION_KEY);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_completion_identity_conflict_rolls_back_without_an_orphan_user(): void
    {
        SocialAccount::factory()->create([
            'provider' => 'google',
            'provider_user_id' => 'google-123',
        ]);

        $response = $this->withSession([PendingSocialIdentity::SESSION_KEY => $this->pendingIdentity()])
            ->post(route('social.registration.store'), ['birth_date' => '2000-01-01']);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('social_auth');
        $response->assertSessionMissing(PendingSocialIdentity::SESSION_KEY);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('social_accounts', 1);
    }

    public function test_same_pending_identity_cannot_create_a_second_account(): void
    {
        $payload = $this->pendingIdentity();

        $this->withSession([PendingSocialIdentity::SESSION_KEY => $payload])
            ->post(route('social.registration.store'), ['birth_date' => '2000-01-01'])
            ->assertRedirect(route('app'));

        $this->post(route('logout'));

        $this->withSession([PendingSocialIdentity::SESSION_KEY => $payload])
            ->post(route('social.registration.store'), ['birth_date' => '2000-01-01'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('social_auth');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('social_accounts', 1);
    }

    private function performCallback(string $provider)
    {
        return $provider === 'apple'
            ? $this->post('/auth/apple/callback')
            : $this->get('/auth/google/callback');
    }

    private function socialiteUser(
        string $provider,
        string|false|null $id = false,
        ?string $email = 'new@example.com',
        bool $verified = true,
    ): SocialiteUser {
        $user = SocialiteUser::fake([
            'id' => $id === false ? "{$provider}-123" : $id,
            'email' => $email,
        ]);

        return $user->setRaw($provider === 'google'
            ? ['verified_email' => $verified]
            : ['email_verified' => $verified ? 'true' : 'false']);
    }

    /** @return array{provider: string, provider_user_id: string, email: string} */
    private function pendingIdentity(): array
    {
        return [
            'provider' => 'google',
            'provider_user_id' => 'google-123',
            'email' => 'new@example.com',
        ];
    }
}

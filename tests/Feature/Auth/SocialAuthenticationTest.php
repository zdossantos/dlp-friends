<?php

namespace Tests\Feature\Auth;

use App\Data\PendingSocialIdentity;
use App\Enums\UserStatus;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SocialAuthenticationTest extends TestCase
{
    use RefreshDatabase;

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
            ? $this->post('/auth/apple/callback', ['error' => 'access_denied'])
            : $this->get('/auth/google/callback?error=access_denied');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('social_auth');
        $response->assertSessionMissing(PendingSocialIdentity::SESSION_KEY);
        $this->assertGuest();
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
}

<?php

use App\Actions\CreateSocialUser;
use App\Data\PendingSocialIdentity;
use App\Enums\SocialProvider;
use App\Exceptions\SocialAuthenticationException;
use App\Http\Responses\PasskeyLoginResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;

uses(RefreshDatabase::class);

it('reveals a ban only after a correct password and keeps the user logged out', function () {
    $user = User::factory()->create(['status' => 'banned']);
    $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
    $this->assertGuest();
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();
    expect(session('errors')->first('email'))->toContain(__('moderation.banned'));
});

it('does not reveal the sanction before a valid second factor', function () {
    $user = User::factory()->withTwoFactor()->create(['status' => 'banned']);
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/two-factor-challenge');
    $this->assertGuest();
    $this->post('/two-factor-challenge', ['recovery_code' => 'invalid'])->assertSessionHasErrors();
    expect(json_encode(session('errors')->getBag('default')->getMessages()))->not->toContain(__('moderation.banned'));
    $this->post('/two-factor-challenge', ['recovery_code' => 'recovery-code-1'])->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toContain(__('moderation.banned'));
    $this->assertGuest();
});

it('redirects directly to login with the ban message after a valid HTML second factor', function () {
    $user = User::factory()->withTwoFactor()->create(['status' => 'banned']);
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/two-factor-challenge');
    $response = $this->from('/two-factor-challenge')->post('/two-factor-challenge', ['recovery_code' => 'recovery-code-1']);
    expect($response->headers->get('Location'))->toBe(route('login'));
    $this->get('/login')->assertInertia(fn (Assert $page) => $page
        ->where('errors.email', fn (string $message): bool => str_contains($message, __('moderation.banned'))));
    $this->assertGuest();
});

it('rejects a verified linked Google identity and prevents registering its retained identifiers', function () {
    $user = User::factory()->create(['status' => 'banned']);
    $user->socialAccounts()->create(['provider' => 'google', 'provider_user_id' => 'retained-google']);
    Socialite::fake('google', (new Laravel\Socialite\Two\User)->map(['id' => 'retained-google', 'email' => $user->email]));
    $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHasErrors('social_auth');
    expect(session('errors')->first('social_auth'))->toContain(__('moderation.banned'));
    $this->assertGuest();
    $this->post('/register', ['email' => $user->email, 'password' => 'ValidPass123!', 'password_confirmation' => 'ValidPass123!', 'birth_date' => '1990-01-01', 'terms_accepted' => true])->assertSessionHasErrors('email');
    expect(fn () => app(CreateSocialUser::class)->execute(new PendingSocialIdentity(SocialProvider::Google, 'retained-google', 'new@example.com'), '1990-01-01'))->toThrow(SocialAuthenticationException::class);
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('social_accounts', 1);
});

it('rejects a completed passkey login with a freshly banned account', function () {
    $user = User::factory()->create();
    $request = Request::create('/passkeys/login', 'POST');
    $request->setLaravelSession(app('session')->driver());
    $this->actingAs($user);
    $request->setUserResolver(fn () => $user);
    $user->newQuery()->whereKey($user->id)->update(['status' => 'banned']);
    expect(fn () => app(PasskeyLoginResponse::class)->toResponse($request))->toThrow(ValidationException::class);
    $this->assertGuest();
});

it('keeps an invalid passkey generic without revealing any ban', function () {
    User::factory()->create(['status' => 'banned']);
    $response = $this->postJson('/passkeys/login', ['credential' => ['id' => 'invalid', 'rawId' => 'invalid', 'type' => 'public-key', 'response' => ['invalid' => true]]])->assertUnprocessable();
    expect($response->getContent())->not->toContain(__('moderation.banned'));
    $this->assertGuest();
});

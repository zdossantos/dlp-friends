<?php

use App\Actions\RequestAccountDeletion;
use App\Actions\SetMemberBan;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $this->sessionDirectory = sys_get_temp_dir().'/dlp-session-tests-'.Str::uuid();
    File::makeDirectory($this->sessionDirectory);
    config(['session.driver' => 'file', 'session.files' => $this->sessionDirectory]);
    if ($port = getenv('SESSION_TEST_REDIS_PORT')) {
        config([
            'session.driver' => 'redis',
            'session.connection' => 'session-tests',
            'database.redis.session-tests' => ['host' => '127.0.0.1', 'port' => (int) $port, 'database' => 0],
        ]);
    }
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');

    // Each cookie jar gets fresh request-local state, as in separate PHP requests.
    $this->client = function (array $cookies, string $method, string $url, array $data = []) {
        Auth::forgetGuards();
        session()->flush();
        $this->defaultCookies = $cookies;

        return $this->{$method}($url, $data);
    };
    $this->loginClient = function (User $user, bool $remember = false): array {
        $response = ($this->client)([], 'post', '/login', ['email' => $user->email, 'password' => 'password', 'remember' => $remember]);
        $response->assertRedirect('/app');

        $cookies = [];
        foreach ($response->headers->getCookies() as $cookie) {
            $cookies[$cookie->getName()] = $response->getCookie($cookie->getName())->getValue();
        }

        return $cookies;
    };
});

test('legacy sessions stay valid until their account is revoked', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->withProfile()->create();
    $cookies = ($this->loginClient)($member);
    session()->forget('auth_session_version');
    session()->save();

    ($this->client)($cookies, 'get', '/settings/account')->assertOk();
    app(SetMemberBan::class)->handle($admin, $member, true, 'Test');
    app(SetMemberBan::class)->handle($admin, $member, false, 'Reviewed');
    ($this->client)($cookies, 'get', '/settings/account')->assertRedirect(route('login'));
});

test('a request that saves an old session after revocation cannot restore access', function () {
    $member = User::factory()->withProfile()->create();
    $cookies = ($this->loginClient)($member);
    $inFlightSession = clone session()->driver();
    app(RequestAccountDeletion::class)->handle($member);
    $inFlightSession->save();

    ($this->client)($cookies, 'getJson', '/settings/account')->assertUnauthorized();
    $this->assertGuest();
});

test('revocation rolls back with its account transaction', function () {
    $member = User::factory()->withProfile()->create();
    $cookies = ($this->loginClient)($member);

    try {
        DB::transaction(function () use ($member) {
            app(RequestAccountDeletion::class)->handle($member);
            throw new RuntimeException('Rollback requested');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Rollback requested');
    }

    ($this->client)($cookies, 'get', '/settings/account')->assertOk();
});

test('remember cookies reconnect current sessions but cannot bypass account revocation', function () {
    $member = User::factory()->withProfile()->create();
    $cookies = ($this->loginClient)($member, remember: true);
    unset($cookies[config('session.cookie')]);
    ($this->client)($cookies, 'get', '/settings/account')->assertOk();

    app(RequestAccountDeletion::class)->handle($member);
    $member->forceFill(['status' => UserStatus::Active, 'deletion_requested_at' => null])->save();
    ($this->client)($cookies, 'get', '/settings/account')->assertRedirect(route('login'));
});

test('second factor authentication stamps the current account version after completion', function () {
    $member = User::factory()->withProfile()->withTwoFactor()->create(['session_version' => 4]);
    $response = ($this->client)([], 'post', '/login', ['email' => $member->email, 'password' => 'password']);
    $response->assertRedirect('/two-factor-challenge')->assertSessionMissing('auth_session_version');
    $cookies = [config('session.cookie') => $response->getCookie(config('session.cookie'))->getValue()];
    $response = ($this->client)($cookies, 'post', '/two-factor-challenge', ['recovery_code' => 'recovery-code-1']);
    $response->assertRedirect('/app');
    $cookies = [config('session.cookie') => $response->getCookie(config('session.cookie'))->getValue()];

    ($this->client)($cookies, 'get', '/settings/account')->assertOk();
});

test('a linked Google login opens a current session after a previous revocation', function () {
    $member = User::factory()->withProfile()->create(['session_version' => 4]);
    $member->socialAccounts()->create(['provider' => 'google', 'provider_user_id' => 'session-google']);
    Socialite::fake('google', (new SocialiteUser)->map(['id' => 'session-google', 'email' => $member->email]));
    $response = ($this->client)([], 'get', '/auth/google/callback');
    $response->assertRedirect('/app');
    $cookies = [config('session.cookie') => $response->getCookie(config('session.cookie'))->getValue()];

    ($this->client)($cookies, 'get', '/settings/account')->assertOk();
});

afterEach(function () {
    File::deleteDirectory($this->sessionDirectory);
});

test('a dormant session cannot return after a ban is lifted while other accounts remain connected', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->withProfile()->create();
    $other = User::factory()->withProfile()->create();
    $first = ($this->loginClient)($member);
    $second = ($this->loginClient)($member);
    $unrelated = ($this->loginClient)($other);
    ($this->client)($second, 'get', '/settings/account')->assertOk();

    app(SetMemberBan::class)->handle($admin, $member, true, 'Test');
    app(SetMemberBan::class)->handle($admin, $member, false, 'Reviewed');

    ($this->client)($first, 'get', '/settings/account')->assertRedirect(route('login'));
    ($this->client)($second, 'get', '/settings/account')->assertRedirect(route('login'));
    ($this->client)($unrelated, 'get', '/settings/account')->assertOk();
    $reconnected = ($this->loginClient)($member->fresh());
    ($this->client)($reconnected, 'get', '/settings/account')->assertOk();
});

test('account deletion revokes silent sessions even if the account is later restored', function () {
    $member = User::factory()->withProfile()->create();
    $silent = ($this->loginClient)($member);
    app(RequestAccountDeletion::class)->handle($member);

    ($this->client)($silent, 'get', '/settings/account')->assertRedirect(route('login'));
    $member->forceFill(['status' => UserStatus::Active, 'deletion_requested_at' => null])->save();
    ($this->client)($silent, 'get', '/settings/account')->assertRedirect(route('login'));
});

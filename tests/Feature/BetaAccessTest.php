<?php

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Http\Middleware\EnsureDeploymentReady;
use App\Models\PartnerProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()->instance('env', 'beta');
    $this->withoutMiddleware([EnsureDeploymentReady::class, PreventRequestForgery::class]);
});

it('requires login for beta pages without an IP restriction', function () {
    $this->withHeader('X-Forwarded-For', '203.0.113.17')->get('/fr')->assertRedirect(route('login'));
    $this->getJson('/fr')->assertUnauthorized();
    $this->get('/login')->assertOk();
    $this->get('/forgot-password')->assertOk();
    $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /');
    $this->get('/up')->assertOk();
});

it('refuses beta pages to members and partners', function (string $factoryState) {
    $user = User::factory()->{$factoryState}()->create();
    $this->actingAs($user)->get('/fr')->assertForbidden();
    $this->get('/app')->assertForbidden();
    $this->post(route('logout'))->assertRedirect();
    $this->assertGuest();
})->with(['withProfile', 'partnerOnly']);

it('allows an active administrator and rechecks a removed role on the next request', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get('/fr')->assertRedirect();
    $this->get(route('dashboard'))->assertOk();
    $admin->roles()->detach(Role::query()->where('name', RoleName::Admin)->firstOrFail());
    $this->get('/fr')->assertForbidden();
});

it('refuses inactive administrators', function (UserStatus $status) {
    $admin = User::factory()->admin()->create(['status' => $status]);
    $this->actingAs($admin)->get('/fr')->assertForbidden();
})->with([UserStatus::Banned, UserStatus::PendingDeletion]);

it('protects even a directly requested public partner image', function () {
    Storage::fake();
    $profile = PartnerProfile::factory()->published()->create();
    Storage::put($profile->publishedRevision->image_path, 'private-beta-image');
    $url = route('partner-profiles.image', $profile);
    $this->get($url)->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();
});

it('protects the private channel authorization endpoint', function () {
    config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'key', 'broadcasting.connections.reverb.secret' => 'secret', 'broadcasting.connections.reverb.app_id' => 'test']);
    Broadcast::purge();
    require base_path('routes/channels.php');
    $user = User::factory()->create();
    $this->actingAs($user)->postJson('/broadcasting/auth', ['socket_id' => '1.2', 'channel_name' => "private-App.Models.User.{$user->id}"])->assertForbidden();
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->postJson('/broadcasting/auth', ['socket_id' => '1.2', 'channel_name' => "private-App.Models.User.{$admin->id}"])->assertOk();
});

it('keeps administrator login and its two factor challenge available', function () {
    $admin = User::factory()->admin()->withTwoFactor()->create();
    $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password'])->assertRedirect(route('two-factor.login'));
    $this->get(route('two-factor.login'))->assertOk();
});

it('prevents self registration in beta', function () {
    $this->get(route('register'))->assertForbidden();
    $this->post(route('register.store'), [])->assertForbidden();
    $this->get(route('social.registration.create'))->assertForbidden();
    $this->post(route('social.registration.store'), [])->assertForbidden();
});

it('keeps public and member production access unchanged', function () {
    app()->instance('env', 'production');
    $this->get('/fr')->assertOk();
    $this->get(route('register'))->assertOk();
    $this->actingAs(User::factory()->withProfile()->create())->get('/app')->assertRedirect(route('discovery.index'));
});

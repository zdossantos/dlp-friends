<?php

use App\Enums\RoleName;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an unknown url returns the localized Inertia not found page with a real 404 status', function (string $locale, string $message, string $destination) {
    $this->withCookie('locale', $locale)
        ->get('/missing-private-looking-resource/42')
        ->assertNotFound()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Errors/NotFound')
            ->where('homeUrl', $destination)
            ->where('inertiaHome', false)
            ->where('i18n.locale', $locale)
            ->where('i18n.messages.common.errors.not_found_description', $message)
        );
})->with([
    'French' => ['fr', 'La page que tu cherches est introuvable ou n’est plus disponible.', '/fr'],
    'English' => ['en', 'The page you are looking for cannot be found or is no longer available.', '/en'],
]);

test('an Inertia navigation receives the same not found component and status', function () {
    $assetVersion = app(HandleInertiaRequests::class)->version(request());

    $this->withCookie('locale', 'fr')
        ->withHeader('X-Inertia', 'true')
        ->withHeader('X-Inertia-Version', $assetVersion)
        ->get('/route-inertia-inconnue')
        ->assertNotFound()
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'Errors/NotFound')
        ->assertJsonPath('props.homeUrl', '/fr');
});

test('signed in accounts return from a not found page to their authorized home', function (User $account, string $routeName) {
    $this->actingAs($account)
        ->get('/still-missing')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Errors/NotFound')
            ->where('homeUrl', route($routeName, absolute: false))
            ->where('inertiaHome', true)
        );
})->with([
    'member' => fn () => [User::factory()->withProfile()->create(), 'app'],
    'partner' => function () {
        $account = User::factory()->partnerOnly()->create();
        $account->roles()->sync([Role::query()->where('name', RoleName::Partner)->firstOrFail()->id]);

        return [$account, 'partner.profile.edit'];
    },
    'admin' => function () {
        $account = User::factory()->admin()->create();
        $account->roles()->sync([Role::query()->where('name', RoleName::Admin)->firstOrFail()->id]);

        return [$account, 'dashboard'];
    },
]);

test('the not found response never repeats the requested private-looking path', function () {
    $this->get('/private-resource/secret-profile-token')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Errors/NotFound')
            ->missing('requestedPath')
        );
});

test('unknown mutation paths stay not found while an existing path with the wrong method stays method not allowed', function () {
    $this->post('/onboarding/missing-action')->assertNotFound();
    $this->post('/auth/google/callback')->assertMethodNotAllowed();
});

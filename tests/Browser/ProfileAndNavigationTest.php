<?php

use App\Http\Middleware\EnsureProfileIsComplete;
use App\Models\Interest;
use App\Models\InterestSetting;
use App\Models\User;

test('profile onboarding exposes the complete accessible contract', function () {
    $user = User::factory()->create();
    Interest::factory()->create(['name' => 'Attractions']);
    Interest::factory()->create(['name' => 'Spectacles']);
    $this->actingAs($user);

    visit('/profile/create')
        ->on()->mobile()
        ->assertSee('Créons votre profil')
        ->assertPresent('input[name="display_name"]')
        ->assertPresent('textarea[name="bio"]')
        ->assertAttribute('input[name="display_name"]', 'maxlength', '80')
        ->assertAttribute('textarea[name="bio"]', 'maxlength', '500')
        ->assertPresent('select[name="visit_frequency"]')
        ->assertPresent('select[name="visibility"]')
        ->assertSee('Visible dans les suggestions')
        ->assertSee('Créer mon profil')
        ->assertSee('Attractions')
        ->assertSee('Spectacles')
        ->assertScript(
            "document.querySelector('main').getBoundingClientRect().top < 100",
            true,
        )
        ->assertScript(
            "document.querySelector('[data-test=member-bottom-navigation]') === null",
            true,
        )
        ->assertNoJavaScriptErrors();
});

test('interest selection disables only unselected choices at the limit', function () {
    $user = User::factory()->create();
    InterestSetting::current()->update(['max_selections' => 1]);
    Interest::factory()->create(['name' => 'Attractions']);
    Interest::factory()->create(['name' => 'Spectacles']);
    $this->actingAs($user);

    $page = visit('/profile/create')->click('Attractions');

    $page->assertScript(
        "document.querySelector('[aria-label=\"Retirer Attractions\"]').disabled",
        false,
    )->assertScript(
        "document.querySelector('[aria-label=\"Ajouter Spectacles\"]').disabled",
        true,
    );

    $page->click('Attractions')->assertScript(
        "document.querySelector('[aria-label=\"Ajouter Spectacles\"]').disabled",
        false,
    );
});

test('profile validation displays a changed interest limit', function () {
    $user = User::factory()->create();
    InterestSetting::current()->update(['max_selections' => 2]);
    Interest::factory()->create(['name' => 'Attractions']);
    Interest::factory()->create(['name' => 'Spectacles']);
    $this->actingAs($user);

    $page = visit('/profile/create')
        ->fill('display_name', 'Aurore')
        ->select('visit_frequency', 'sometimes')
        ->click('Attractions')
        ->click('Spectacles');

    InterestSetting::current()->update(['max_selections' => 1]);

    $page->click('Créer mon profil')
        ->assertSee('Vous pouvez sélectionner au maximum 1 intérêts.');
});

test('a refreshed catalog drops an archived selected interest', function () {
    $interest = Interest::factory()->create(['name' => 'Attractions']);
    $user = User::factory()->withProfile()->create();
    $user->profile?->interests()->attach($interest, ['is_selected' => true]);
    $this->actingAs($user);

    visit('/profile/edit')
        ->assertSee('Attractions')
        ->assertPresent("input[name='interest_ids[]'][value='{$interest->id}']");

    $interest->update(['is_active' => false]);

    visit('/profile/edit')
        ->assertDontSee('Attractions')
        ->assertNotPresent("input[name='interest_ids[]'][value='{$interest->id}']");
});

test('a completed member sees their public profile and member actions', function () {
    $interest = Interest::factory()->create(['name' => 'Chill']);
    $user = User::factory()->withProfile()->create([
        'birth_date' => today()->subYears(26),
    ]);
    $user->profile?->update([
        'display_name' => 'Aurore',
        'bio' => 'Fan des attractions',
        'visit_frequency' => 'often',
    ]);
    $user->profile?->interests()->attach($interest, ['is_selected' => true]);
    $this->actingAs($user);

    visit('/profile')
        ->assertSee('Aurore')
        ->assertSee('26 ans')
        ->assertSee('Fan des attractions')
        ->assertSee('Souvent')
        ->assertSee('Visible')
        ->assertSee('Chill')
        ->assertSeeLink('Modifier mon profil')
        ->assertPresent('[aria-label="Réglages"]')
        ->assertNotPresent('[aria-label="Administration"]')
        ->assertPresent('[aria-label="Se déconnecter"]');
});

test('an administrator sees administration and member return navigation', function () {
    $admin = User::factory()->withProfile()->admin()->create();
    $admin->profile?->update(['display_name' => 'Admin Aurore']);
    $this->actingAs($admin);

    visit('/profile')->assertPresent('[aria-label="Administration"]');

    visit('/dashboard')
        ->assertSeeLink('Intérêts')
        ->assertSeeLink('Retour au profil')
        ->assertSee('Admin Aurore');
});

test('administration identity falls back to email without a profile', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'admin@example.test',
    ]);
    $this->withoutMiddleware(EnsureProfileIsComplete::class);
    $this->actingAs($admin);

    visit('/dashboard')->assertSee('admin@example.test');
});

test('member navigation exposes only implemented destinations and tracks settings', function () {
    $user = User::factory()->withProfile()->create();
    $this->actingAs($user);

    visit('/discover')
        ->on()->mobile()
        ->assertCount('[data-test="member-bottom-navigation"] a', 2)
        ->assertPresent('[aria-label="Découvrir"][aria-current="page"]')
        ->assertPresent('[aria-label="Profil"]');

    visit('/settings/account')
        ->on()->mobile()
        ->assertPresent('[aria-label="Profil"][aria-current="page"]')
        ->assertSee('Réglages du compte');
});

test('member layout has no header and reserves safe dock space', function () {
    $user = User::factory()->withProfile()->create();
    $this->actingAs($user);

    visit('/profile')
        ->on()->mobile()
        ->assertScript("document.querySelector('header') === null", true)
        ->assertScript(
            "getComputedStyle(document.querySelector('[data-test=member-shell-content]')).paddingBottom !== '0px'",
            true,
        );
});

test('logging out removes access to the private profile', function () {
    $user = User::factory()->withProfile()->create();
    $user->profile?->update(['display_name' => 'Aurore privée']);
    $this->actingAs($user);

    $page = visit('/profile');
    $page->script("sessionStorage.setItem('historyKey', 'private'); localStorage.setItem('appearance', 'dark'); true;");
    $page->click('[aria-label="Se déconnecter"]')
        ->assertPathIs('/')
        ->assertDontSee('Aurore privée')
        ->assertScript("sessionStorage.getItem('historyKey')", null)
        ->assertScript("localStorage.getItem('appearance')", 'dark');

    $this->assertGuest();
});

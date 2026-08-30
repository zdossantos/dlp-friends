<?php

use App\Enums\ProductOnboardingStatus;
use App\Http\Middleware\EnsureProfileIsComplete;
use App\Models\Avatar;
use App\Models\Interest;
use App\Models\InterestSetting;
use App\Models\ProductOnboardingSetting;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('profile onboarding requires an active avatar and renders its two-color gradient', function () {
    Storage::fake('local');
    $active = Avatar::factory()->create([
        'name' => 'Aurore',
        'image_path' => 'avatars/aurore.png',
        'primary_color' => '#7C3AED',
        'secondary_color' => '#EC4899',
        'is_active' => true,
        'sort_order' => 0,
    ]);
    Avatar::factory()->create([
        'name' => 'Archivé',
        'is_active' => false,
        'sort_order' => 1,
    ]);
    Storage::disk('local')->put($active->image_path, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL8WQAAAABJRU5ErkJggg==',
    ));
    $user = User::factory()->create();
    $this->actingAs($user);

    visit('/profile/create')
        ->assertSee('Ton avatar')
        ->assertSee('1 sur 4')
        ->assertSee('Aurore')
        ->assertDontSee('Archivé')
        ->assertPresent("input[name='avatar_id'][value='{$active->id}']")
        ->assertPresent('img[alt="Avatar Aurore"]')
        ->assertScript(
            "document.querySelector('[data-test=avatar-option-{$active->id}]').style.backgroundImage.includes('rgb(124, 58, 237)')",
            true,
        )
        ->assertPresent('[data-test="avatar-carousel"][tabindex="0"]')
        ->assertNoJavaScriptErrors();
});

test('profile onboarding is a keyboard accessible four-step journey that preserves values', function () {
    Storage::fake('local');
    $first = Avatar::factory()->create(['name' => 'Aurore', 'sort_order' => 0]);
    $second = Avatar::factory()->create(['name' => 'Nova', 'sort_order' => 1]);
    $third = Avatar::factory()->create(['name' => 'Sélène', 'sort_order' => 2]);
    $fourth = Avatar::factory()->create(['name' => 'Orion', 'sort_order' => 3]);
    foreach ([$first, $second, $third, $fourth] as $avatar) {
        Storage::disk('local')->put($avatar->image_path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL8WQAAAABJRU5ErkJggg==',
        ));
    }
    Interest::factory()->create(['name' => 'Attractions']);
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = visit('/profile/create')
        ->on()->mobile()
        ->assertSee('Ton avatar')
        ->assertScript('document.documentElement.scrollHeight <= document.documentElement.clientHeight', true)
        ->assertAttribute("[data-test=avatar-carousel-item-{$first->id}] img", 'draggable', 'false')
        ->assertScript("getComputedStyle(document.querySelector('[data-test=avatar-carousel-item-{$first->id}]')).transitionDuration !== '0s'", true)
        ->assertScript("document.querySelector('[data-test=avatar-carousel-item-{$second->id}]').style.transform.includes('rotate')", true)
        ->assertAttribute("[data-test=avatar-carousel-item-{$third->id}]", 'tabindex', '-1')
        ->assertAttribute("[data-test=avatar-carousel-item-{$third->id}]", 'aria-hidden', 'true');

    $page->script("const card = document.querySelector('[data-test=avatar-carousel-item-{$first->id}]'); card.dispatchEvent(new PointerEvent('pointerdown', { pointerId: 21, clientX: 240, clientY: 300, bubbles: true })); card.dispatchEvent(new PointerEvent('pointerup', { pointerId: 21, clientX: 140, clientY: 300, bubbles: true }));");

    $page
        ->assertScript("document.querySelector('input[name=avatar_id][value=\"{$second->id}\"]').checked", true);

    $page->script("document.querySelector('[aria-label=\"Choisir Aurore\"]').click()");

    $page
        ->assertScript("document.querySelector('input[name=avatar_id][value=\"{$first->id}\"]').checked", true)
        ->click('[aria-label="Avatar suivant"]')
        ->assertScript("document.querySelector('input[name=avatar_id][value=\"{$second->id}\"]').checked", true)
        ->assertSee('Nova')
        ->keys('[data-test="avatar-carousel"]', 'ArrowLeft')
        ->assertScript("document.querySelector('input[name=avatar_id][value=\"{$first->id}\"]').checked", true)
        ->keys('[data-test="avatar-carousel"]', 'ArrowRight')
        ->click('Suivant')
        ->assertSee('Ton identité')
        ->assertSee('2 sur 4')
        ->assertScript('document.documentElement.scrollHeight <= document.documentElement.clientHeight', true)
        ->fill('display_name', 'Camille')
        ->fill('bio', 'Toujours partante pour une journée entre fans.')
        ->click('Suivant')
        ->assertSee('Tes univers')
        ->assertScript('document.documentElement.scrollHeight <= document.documentElement.clientHeight', true)
        ->resize(320, 568)
        ->assertScript("getComputedStyle(document.querySelector('[data-test=profile-step-content-3]')).overflowY === 'auto'", true)
        ->assertScript("document.querySelector('[data-test=profile-step-content-3]').scrollHeight >= document.querySelector('[data-test=profile-step-content-3]').clientHeight", true)
        ->resize(375, 812)
        ->click('Attractions')
        ->click('Souvent')
        ->click('Suivant')
        ->assertSee('Ton aperçu')
        ->assertSee('4 sur 4')
        ->assertScript('document.documentElement.scrollHeight <= document.documentElement.clientHeight', true)
        ->assertPresent('[data-test="profile-preview"] [data-test="discovery-avatar-hero"]')
        ->assertSee('Camille')
        ->assertSee('Toujours partante pour une journée entre fans.')
        ->click('Retour')
        ->assertSee('Tes univers')
        ->click('Retour')
        ->assertSee('Ton identité')
        ->assertValue('display_name', 'Camille')
        ->assertValue('bio', 'Toujours partante pour une journée entre fans.')
        ->assertScript('document.documentElement.scrollWidth <= document.documentElement.clientWidth', true)
        ->assertNoJavaScriptErrors();

    expect($first->id)->not->toBe($second->id);
});

test('profile onboarding exposes the complete accessible contract', function () {
    $user = User::factory()->create();
    Interest::factory()->create(['name' => 'Attractions']);
    Interest::factory()->create(['name' => 'Spectacles']);
    $this->actingAs($user);

    visit('/profile/create')
        ->on()->mobile()
        ->assertSee('Créons ton profil')
        ->assertSee('Ton avatar')
        ->assertPresent('input[name="display_name"]')
        ->assertPresent('textarea[name="bio"]')
        ->assertAttribute('input[name="display_name"]', 'maxlength', '80')
        ->assertAttribute('textarea[name="bio"]', 'maxlength', '500')
        ->assertPresent('input[type="radio"][name="visit_frequency"]')
        ->assertNotPresent('select[name="visit_frequency"]')
        ->assertNotPresent('select[name="visibility"]')
        ->assertPresent('button#visibility[data-slot="select-trigger"]')
        ->assertPresent('input[type="hidden"][name="visibility"]')
        ->assertSee('Suivant')
        ->assertPresent('[aria-label="Progression du profil"]')
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

test('profile visibility can be created and edited with the accessible select keyboard controls', function () {
    Storage::fake('local');
    $avatar = Avatar::factory()->create(['name' => 'Aurore']);
    $demoAvatar = Avatar::factory()->create(['name' => 'Nova']);
    ProductOnboardingSetting::query()->create([
        'id' => ProductOnboardingSetting::SINGLETON_ID,
        'pass_avatar_id' => $avatar->id,
        'like_avatar_id' => $demoAvatar->id,
    ]);
    foreach ([$avatar, $demoAvatar] as $storedAvatar) {
        Storage::disk('local')->put($storedAvatar->image_path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL8WQAAAABJRU5ErkJggg==',
        ));
    }
    $user = User::factory()->create();
    $this->actingAs($user);

    visit('/profile/create')
        ->on()->mobile()
        ->click('Suivant')
        ->fill('display_name', 'Aurore')
        ->click('Suivant')
        ->click('Souvent')
        ->click('Suivant')
        ->assertSee('Visible')
        ->assertPresent('button#visibility[data-slot="select-trigger"]')
        ->keys('#visibility', 'Enter')
        ->keys('[data-slot="select-item"]:last-child', 'Enter')
        ->assertValue('input[name="visibility"]', 'hidden')
        ->click('Créer mon profil')
        ->assertPathIs('/onboarding');

    $profile = $user->fresh()->profile;

    expect($profile?->visibility->value)->toBe('hidden');

    $user->productOnboarding()->updateOrCreate([], [
        'status' => ProductOnboardingStatus::Completed,
        'step' => null,
    ]);

    visit('/profile/edit')
        ->click('[data-test="avatar-carousel-item-'.$profile->avatar_id.'"]')
        ->click('Suivant')
        ->click('Suivant')
        ->click('Suivant')
        ->assertSee('Masqué')
        ->keys('#visibility', 'Enter')
        ->keys('[data-slot="select-item"]:first-child', 'Enter')
        ->assertValue('input[name="visibility"]', 'visible')
        ->click('Enregistrer')
        ->assertPathIs('/profile');

    expect($user->fresh()->profile?->visibility->value)->toBe('visible');
});

test('interest selection disables only unselected choices at the limit', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $avatar = Avatar::factory()->create();
    Storage::disk('local')->put($avatar->image_path, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL8WQAAAABJRU5ErkJggg==',
    ));
    InterestSetting::current()->update(['max_selections' => 1]);
    Interest::factory()->create(['name' => 'Attractions']);
    Interest::factory()->create(['name' => 'Spectacles']);
    $this->actingAs($user);

    $page = visit('/profile/create')
        ->click('Suivant')
        ->fill('display_name', 'Aurore')
        ->click('Suivant')
        ->click('Attractions');

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
    Storage::fake('local');
    $user = User::factory()->create();
    $avatar = Avatar::factory()->create(['name' => 'Avatar Aurore']);
    Storage::disk('local')->put($avatar->image_path, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL8WQAAAABJRU5ErkJggg==',
    ));
    InterestSetting::current()->update(['max_selections' => 2]);
    Interest::factory()->create(['name' => 'Attractions']);
    Interest::factory()->create(['name' => 'Spectacles']);
    $this->actingAs($user);

    $page = visit('/profile/create')
        ->click('Suivant')
        ->fill('display_name', 'Aurore')
        ->click('Suivant')
        ->click('De temps en temps')
        ->click('Attractions')
        ->click('Spectacles')
        ->click('Suivant');

    InterestSetting::current()->update(['max_selections' => 1]);

    $page->click('Créer mon profil')
        ->assertSee('Tu peux sélectionner au maximum un univers favori.');
});

test('a refreshed catalog drops an archived selected interest', function () {
    Storage::fake('local');
    $interest = Interest::factory()->create(['name' => 'Attractions']);
    $user = User::factory()->withProfile()->create();
    $user->profile?->interests()->attach($interest, ['is_selected' => true]);
    Storage::disk('local')->put($user->profile->avatar->image_path, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL8WQAAAABJRU5ErkJggg==',
    ));
    $this->actingAs($user);

    visit('/profile/edit')
        ->on()->mobile()
        ->assertDontSee('Modifier mon profil')
        ->assertScript(
            "document.querySelector('[data-test=member-bottom-navigation]') === null",
            true,
        )
        ->assertScript('document.documentElement.scrollHeight <= document.documentElement.clientHeight', true)
        ->click('Suivant')
        ->assertPresent('[data-test="profile-form-footer"]')
        ->assertScript(
            "getComputedStyle(document.querySelector('[data-test=profile-form-footer]')).borderTopWidth === '0px'",
            true,
        )
        ->assertScript(
            "getComputedStyle(document.querySelector('[data-test=profile-form-footer]')).backgroundColor === 'rgba(0, 0, 0, 0)'",
            true,
        )
        ->assertScript(
            "getComputedStyle(document.querySelector('[data-test=profile-back-button]')).backgroundColor !== 'rgba(0, 0, 0, 0)'",
            true,
        )
        ->click('Suivant')
        ->assertSee('Attractions')
        ->assertPresent("input[name='interest_ids[]'][value='{$interest->id}']");

    $interest->update(['is_active' => false]);

    visit('/profile/edit')
        ->click('Suivant')
        ->click('Suivant')
        ->assertDontSee('Attractions')
        ->assertNotPresent("input[name='interest_ids[]'][value='{$interest->id}']");
});

test('a completed member sees their public profile and member actions', function () {
    Storage::fake('local');
    $interests = Interest::factory()->count(5)->sequence(
        ['name' => 'Chill'],
        ['name' => 'Attractions'],
        ['name' => 'Pins'],
        ['name' => 'Food'],
        ['name' => 'Spectacles'],
    )->create();
    $user = User::factory()->withProfile()->create([
        'birth_date' => today()->subYears(26),
    ]);
    $user->profile?->update([
        'display_name' => 'Aurore',
        'bio' => str_repeat('Fan des attractions et des spectacles. ', 8),
        'visit_frequency' => 'often',
    ]);
    $user->profile?->interests()->attach($interests->modelKeys(), ['is_selected' => true]);
    Storage::disk('local')->put($user->profile->avatar->image_path, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL8WQAAAABJRU5ErkJggg==',
    ));
    $this->actingAs($user);

    visit('/profile')
        ->on()->mobile()
        ->assertSee('Aurore')
        ->assertSee('26 ans')
        ->assertSee('Fan des attractions')
        ->assertSee('Souvent')
        ->assertSee('Visible')
        ->assertSee('Chill')
        ->assertSeeLink('Modifier mon profil')
        ->assertPresent('[data-test="profile-avatar-hero"]')
        ->assertPresent('[data-test="profile-information-sheet"]')
        ->assertScript(
            "getComputedStyle(document.querySelector('[data-test=profile-information-sheet]')).overflowY === 'auto'",
            true,
        )
        ->assertScript(
            "document.querySelector('[data-test=profile-information-sheet]').scrollHeight > document.querySelector('[data-test=profile-information-sheet]').clientHeight",
            true,
        )
        ->assertScript(
            "(() => { const style = getComputedStyle(document.querySelector('[data-test=profile-information-sheet]')); return style.paddingTop === style.paddingRight && style.paddingRight === style.paddingBottom && style.paddingBottom === style.paddingLeft; })()",
            true,
        )
        ->assertScript(
            "document.querySelector('[data-test=profile-about-title]').getBoundingClientRect().top < document.querySelector('[data-test=profile-interests-title]').getBoundingClientRect().top",
            true,
        )
        ->assertScript(
            "document.querySelector('[data-test=profile-avatar-hero]').getBoundingClientRect().height >= 176 && document.querySelector('[data-test=profile-avatar-hero]').getBoundingClientRect().height <= 224",
            true,
        )
        ->assertScript(
            'document.querySelector(\'[data-test=member-shell-content]\').scrollHeight <= document.querySelector(\'[data-test=member-shell-content]\').clientHeight',
            true,
        )
        ->assertScript(
            "document.querySelector('[data-test=profile-card]').getBoundingClientRect().bottom <= document.querySelector('[data-test=member-shell-content]').getBoundingClientRect().bottom",
            true,
        )
        ->assertScript(
            'document.documentElement.scrollWidth <= document.documentElement.clientWidth',
            true,
        )
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
        ->assertPresent('[data-test="app-logo-icon"]')
        ->assertAttribute('[data-test="app-logo-icon"]', 'aria-hidden', 'true')
        ->assertSeeLink('Univers favoris')
        ->assertSeeLink('Retour au profil')
        ->assertSee('Admin Aurore');
});

test('administration identity falls back to email without a profile', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'admin@example.test',
    ]);
    $admin->productOnboarding()->create([
        'status' => ProductOnboardingStatus::Completed,
    ]);
    $this->withoutMiddleware(EnsureProfileIsComplete::class);
    $this->actingAs($admin);

    visit('/dashboard')->assertSee('admin@example.test');
});

test('member navigation appears on discovery conversations profile and settings pages', function () {
    $user = User::factory()->withProfile()->create();
    $this->actingAs($user);
    $this->withSession(['auth.password_confirmed_at' => time()]);

    visit('/discover')
        ->on()->mobile()
        ->assertCount('[data-test="member-bottom-navigation"] a', 3)
        ->assertPresent('[aria-label="Explorer"][aria-current="page"]')
        ->assertPresent('[aria-label="Échanges"]')
        ->assertPresent('[aria-label="Profil"]');

    visit('/conversations')
        ->on()->mobile()
        ->assertPresent('[data-test="member-bottom-navigation"]')
        ->assertPresent('[aria-label="Échanges"][aria-current="page"]');

    visit('/profile')
        ->on()->mobile()
        ->assertPresent('[data-test="member-bottom-navigation"]')
        ->assertPresent('[aria-label="Profil"][aria-current="page"]');

    visit('/settings/account')
        ->on()->mobile()
        ->assertSee('Réglages du compte')
        ->assertPresent('[data-test="member-bottom-navigation"]')
        ->assertPresent('[aria-label="Profil"][aria-current="page"]')
        ->assertScript(
            "parseFloat(getComputedStyle(document.querySelector('[data-test=member-shell-content]')).paddingBottom) >= 88",
            true,
        );

    visit('/settings/security')
        ->on()->mobile()
        ->assertPresent('[data-test="member-bottom-navigation"]')
        ->assertPresent('[aria-label="Profil"][aria-current="page"]');

    visit('/settings/appearance')
        ->on()->mobile()
        ->assertPresent('[data-test="member-bottom-navigation"]')
        ->assertPresent('[aria-label="Profil"][aria-current="page"]');
});

test('member layout fixes navigation above reserved content space', function () {
    $user = User::factory()->withProfile()->create();
    $this->actingAs($user);

    visit('/profile')
        ->on()->mobile()
        ->assertScript("document.querySelector('header') === null", true)
        ->assertPresent('[data-test="member-bottom-navigation-container"]')
        ->assertScript(
            "getComputedStyle(document.querySelector('[data-test=member-bottom-navigation-container]')).position === 'fixed'",
            true,
        )
        ->assertScript(
            "parseFloat(getComputedStyle(document.querySelector('[data-test=member-shell-content]')).paddingBottom) >= 88",
            true,
        )
        ->assertScript(
            "document.querySelector('[data-test=profile-card]').getBoundingClientRect().bottom + 16 <= document.querySelector('[data-test=member-bottom-navigation-container]').getBoundingClientRect().top",
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

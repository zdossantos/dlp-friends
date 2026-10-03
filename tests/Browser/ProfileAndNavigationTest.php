<?php

use App\Enums\ProductOnboardingStatus;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Http\Middleware\EnsureProfileIsComplete;
use App\Models\Avatar;
use App\Models\Interest;
use App\Models\InterestSetting;
use App\Models\ProductOnboardingSetting;
use App\Models\Role;
use App\Models\SocialAccount;
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
    config()->set('services.google.analytics_id', 'G-TEST123456');

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

    $page = visit('/profile')
        ->on()->mobile()
        ->assertSee('Aurore')
        ->assertSee('26 ans')
        ->assertSee('Fan des attractions')
        ->assertSee('Souvent')
        ->assertSee('Visible')
        ->assertSee('Chill')
        ->assertSeeLink('Modifier mon profil')
        ->assertPresent('[data-test="profile-analytics-consent-settings"]')
        ->assertPresent('[data-test="profile-avatar-hero"]')
        ->assertPresent('[data-test="profile-information-sheet"]')
        ->assertScript(
            "(() => { const badges = [...document.querySelectorAll('[data-test=profile-age-badge], [data-test=profile-visibility-badge], [data-test=profile-frequency-badge], [data-test=profile-interest-badge]')]; return badges.length >= 4 && badges.every((badge) => { const style = getComputedStyle(badge); return style.borderTopWidth === '1px' && style.borderTopStyle === 'solid' && style.borderTopColor !== 'rgba(0, 0, 0, 0)'; }); })()",
            true,
        )
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

    $page->click('[data-analytics-refuse]')
        ->assertScript("document.querySelector('[data-test=analytics-consent-settings]').hidden", true)
        ->assertVisible('[data-test="profile-analytics-consent-settings"]')
        ->click('[data-test="profile-analytics-consent-settings"]')
        ->assertVisible('[data-test="analytics-consent-dialog"]');

    $page->script("localStorage.setItem('appearance', 'dark')");
    $page->navigate('/profile')
        ->assertScript(
            "(() => { const foreground = getComputedStyle(document.body).color; const icons = [...document.querySelectorAll('[data-test=profile-hero-action] svg')]; return icons.length === 2 && icons.every((icon) => getComputedStyle(icon).color === foreground); })()",
            true,
        )
        ->assertScript(
            "(() => { const badges = [...document.querySelectorAll('[data-test=profile-age-badge], [data-test=profile-visibility-badge], [data-test=profile-frequency-badge], [data-test=profile-interest-badge]')]; return document.documentElement.classList.contains('dark') && badges.length >= 4 && badges.every((badge) => { const style = getComputedStyle(badge); return style.borderTopWidth === '1px' && style.borderTopStyle === 'solid' && style.borderTopColor !== 'rgba(0, 0, 0, 0)'; }); })()",
            true,
        )
        ->assertNoJavaScriptErrors();
});

test('an administrator sees administration and member return navigation', function () {
    $admin = User::factory()->withProfile()->admin()->create();
    $admin->profile?->update(['display_name' => 'Admin Aurore']);
    $this->actingAs($admin);

    $page = visit('/profile')
        ->assertPresent('[aria-label="Administration"]')
        ->assertCount('[data-test="profile-hero-action"]', 3)
        ->assertPresent('[data-test="admin-profile-badge"]')
        ->assertSee('Administrateur')
        ->assertScript("document.documentElement.classList.contains('app-viewport')", true)
        ->assertScript(
            "document.querySelector('[data-test=profile-presentation]').classList.contains('border-amber-400') && getComputedStyle(document.querySelector('[data-test=profile-presentation]')).borderTopWidth === '2px'",
            true,
        );

    $page->click('[aria-label="Administration"]')
        ->assertPathIs('/dashboard')
        ->assertScript("document.documentElement.classList.contains('app-viewport')", false)
        ->assertScript(
            "getComputedStyle(document.documentElement).overflowY !== 'hidden' && getComputedStyle(document.body).overflowY !== 'hidden'",
            true,
        )
        ->assertPresent('[data-test="admin-bottom-navigation"]')
        ->assertPresent('[data-test="admin-dashboard-link"][aria-current="page"]')
        ->assertPresent('[data-test="workspace-switcher-trigger"]')
        ->click('[data-test="workspace-switcher-trigger"]')
        ->assertPresent('[data-test="workspace-member-link"]')
        ->assertPresent('[data-test="workspace-admin-link"][aria-current="page"]')
        ->assertSee('Espace administration')
        ->assertNoJavaScriptErrors();
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
        ->assertCount('[data-test="member-bottom-navigation"] a', 5)
        ->assertScript(<<<'JS'
            Array.from(document.querySelectorAll('[data-test="member-bottom-navigation"] a'))
                .map((item) => item.getAttribute('aria-label'))
                .join('|') === 'Découvrir|Conversations|Événements|Notifications|Profil'
            JS, true)
        ->assertPresent('[aria-label="Découvrir"][aria-current="page"]')
        ->assertPresent('[aria-label="Événements"]')
        ->assertPresent('[aria-label="Conversations"]')
        ->assertPresent('[aria-label="Notifications"]')
        ->assertPresent('[aria-label="Profil"]');

    visit('/conversations')
        ->on()->mobile()
        ->assertPresent('[data-test="member-bottom-navigation"]')
        ->assertPresent('[aria-label="Conversations"][aria-current="page"]');

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

test('account deletion explains immediate access loss and the purge deadline in both locales', function () {
    $french = User::factory()->withProfile()->create(['locale' => 'fr']);
    $this->actingAs($french);

    visit('/settings/account')
        ->on()->mobile()
        ->assertSee('L’accès à ton compte cessera immédiatement')
        ->assertSee('sous 30 jours')
        ->click('[data-test="delete-user-button"]')
        ->assertPresent('[data-slot="drawer-content"]')
        ->assertSee('Veux-tu vraiment supprimer ton compte ?');

    $english = User::factory()->withProfile()->create(['locale' => 'en']);
    $this->actingAs($english);

    visit('/settings/account')
        ->assertSee('Access to your account will end immediately')
        ->assertSee('within 30 days');
});

test('social only member can cancel then explicitly confirm account deletion', function () {
    $user = User::factory()->withProfile()->create([
        'locale' => 'fr',
        'password' => null,
    ]);
    SocialAccount::factory()->for($user)->create();
    $this->actingAs($user);

    $page = visit('/settings/account')
        ->on()->mobile()
        ->click('[data-test="delete-user-button"]')
        ->assertPresent('[data-slot="drawer-content"]')
        ->assertSee('Confirme que tu comprends')
        ->assertPresent('#confirm_deletion[data-state="unchecked"]')
        ->click('[data-test="cancel-delete-user-button"]')
        ->assertMissing('[data-slot="drawer-content"]')
        ->click('[data-test="delete-user-button"]')
        ->click('#confirm_deletion')
        ->assertAttribute('#confirm_deletion', 'data-state', 'checked')
        ->click('[data-test="confirm-delete-user-button"]')
        ->assertPathIsNot('/settings/account');

    expect($user->fresh()->status)->toBe(UserStatus::PendingDeletion);
    expect($user->socialAccounts()->exists())->toBeFalse();
    $page->assertNoJavaScriptErrors();
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
        ->assertPathIsNot('/profile')
        ->assertDontSee('Aurore privée')
        ->assertScript("['/fr', '/en'].includes(window.location.pathname)", true)
        ->assertScript("sessionStorage.getItem('historyKey')", null)
        ->assertScript("localStorage.getItem('appearance')", 'dark');

    $this->assertGuest();
});

test('notification consent is explicit accessible and bilingual', function () {
    $french = User::factory()->withProfile()->create(['locale' => 'fr']);
    $this->actingAs($french);

    $page = visit('/settings/notifications')->on()->mobile();
    $page->assertSee('Notifications sur cet appareil');
    $page->script("document.querySelector('[data-test=partner-announcements-switch]').scrollIntoView({ block: 'center' }); true;");
    $page
        ->assertSee('Cette préférence est activée par défaut.')
        ->assertAttribute(
            '[data-test="partner-announcements-switch"]',
            'role',
            'switch',
        )
        ->assertAttribute(
            '[data-test="partner-announcements-switch"]',
            'aria-checked',
            'true',
        )
        ->click('[data-test="partner-announcements-switch"]')
        ->click('[data-test="save-notification-preferences"]')
        ->assertSee('Tes préférences de notifications ont été enregistrées.')
        ->assertNoJavaScriptErrors();

    expect($french->partnerNotificationPreference()->value('enabled'))->toBeFalse();

    $english = User::factory()->withProfile()->create(['locale' => 'en']);
    $this->actingAs($english);

    $page = visit('/settings/notifications');
    $page->assertSee('Notifications on this device');
    $page->script("document.querySelector('[data-test=partner-announcements-switch]').scrollIntoView({ block: 'center' }); true;");
    $page
        ->assertSee('This preference is enabled by default.')
        ->assertSee('Receive partner announcements')
        ->assertNoJavaScriptErrors();
});

test('notification recovery guide opens from a member settings page', function () {
    $member = User::factory()->withProfile()->create(['locale' => 'fr']);
    $this->actingAs($member);

    visit('/settings/notifications')
        ->on()->mobile()
        ->click('[data-test="enable-web-push"]')
        ->assertPresent('[data-test="web-push-invitation"]')
        ->assertSee('Réactiver les notifications')
        ->assertNoJavaScriptErrors();
});

test('partner mobile navigation exposes only implemented partner destinations', function () {
    $partner = User::factory()->partnerOnly()->create();
    $this->actingAs($partner);

    visit('/partner/profile')
        ->on()->mobile()
        ->assertCount('[data-test="member-bottom-navigation"] a', 4)
        ->assertPresent('[aria-label="Profil partenaire"][aria-current="page"]')
        ->assertPresent('[aria-label="Annonces partenaire"]')
        ->assertPresent('[aria-label="Statistiques partenaire"]')
        ->assertPresent('[aria-label="Notifications"]')
        ->assertNoJavaScriptErrors();

    visit('/partner/announcements')
        ->on()->mobile()
        ->assertCount('[data-test="member-bottom-navigation"] a', 4)
        ->assertPresent('[aria-label="Annonces partenaire"][aria-current="page"]')
        ->assertNoJavaScriptErrors();
});

test('a member partner switches workspaces from the bottom navigation', function () {
    $memberPartner = User::factory()->withProfile()->partner()->create();
    $this->actingAs($memberPartner);

    $page = visit('/discover')
        ->on()->mobile()
        ->assertCount('[data-test="member-bottom-navigation"] a', 5)
        ->assertPresent('[data-test="workspace-switcher-trigger"]')
        ->assertPresent('[aria-label="Profil"]')
        ->click('[data-test="workspace-switcher-trigger"]')
        ->assertSee('Changer d’espace')
        ->assertSee('Espace membre')
        ->assertSee('Espace partenaire')
        ->assertDontSeeLink('Profil membre')
        ->assertAttribute(
            '[data-test="workspace-member-link"]',
            'aria-current',
            'page',
        )
        ->click('[data-test="workspace-partner-link"]')
        ->assertPathIs('/partner/profile')
        ->assertCount('[data-test="member-bottom-navigation"] a', 4)
        ->assertPresent('[data-test="workspace-switcher-trigger"]')
        ->assertNoJavaScriptErrors();

    $page->click('[data-test="workspace-switcher-trigger"]')
        ->assertAttribute(
            '[data-test="workspace-partner-link"]',
            'aria-current',
            'page',
        )
        ->click('[data-test="workspace-member-link"]')
        ->assertPathIs('/discover')
        ->assertNoJavaScriptErrors();
});

test('shared workspace switcher exposes authorized destinations and marks the current workspace', function () {
    $administrator = User::factory()->withProfile()->admin()->partner()->create();
    $this->actingAs($administrator);

    $page = visit('/discover')
        ->on()->mobile()
        ->assertPresent('[data-test="workspace-switcher-trigger"]')
        ->click('[data-test="workspace-switcher-trigger"]')
        ->assertSee('Changer d’espace')
        ->assertAttribute('[data-test="workspace-member-link"]', 'aria-current', 'page')
        ->assertPresent('[data-test="workspace-partner-link"]')
        ->assertPresent('[data-test="workspace-admin-link"]')
        ->assertAttribute('[data-test="workspace-admin-link"]', 'aria-label', 'Espace administration');

    $page->keys('[data-test="workspace-admin-link"]', 'Escape')
        ->assertScript(
            "document.activeElement === document.querySelector('[data-test=workspace-switcher-trigger]')",
            true,
        )
        ->assertNoJavaScriptErrors();
});

test('workspace switcher exposes exactly the workspaces authorized by the role matrix', function (
    array $roles,
    string $path,
    array $expectedDestinations,
) {
    $account = User::factory()->withProfile()->create();
    $roleIds = Role::query()
        ->whereIn('name', $roles)
        ->pluck('id');
    $account->roles()->sync($roleIds);
    $account->refresh();
    $this->actingAs($account);

    $page = visit($path)->on()->mobile();

    if (count($expectedDestinations) === 1) {
        $page->assertMissing('[data-test="workspace-switcher-trigger"]')
            ->assertNoJavaScriptErrors();

        return;
    }

    $expectedTestIds = array_map(
        fn (string $destination): string => 'workspace-'.$destination.'-link',
        $expectedDestinations,
    );
    sort($expectedTestIds);

    $currentWorkspace = str_starts_with($path, '/admin') || $path === '/dashboard'
        ? 'admin'
        : (str_starts_with($path, '/partner') ? 'partner' : 'member');

    $page->assertAttribute(
        '[data-test="workspace-switcher-trigger"]',
        'data-workspaces',
        implode(',', $expectedTestIds),
    )->assertAttribute(
        '[data-test="workspace-switcher-trigger"]',
        'data-current-workspace',
        'workspace-'.$currentWorkspace.'-link',
    )->assertNoJavaScriptErrors();
})->with([
    'member only' => [['user'], '/discover', ['member']],
    'partner only' => [['partner'], '/partner/profile', ['partner']],
    'administrator only' => [['admin'], '/dashboard', ['admin']],
    'member and partner' => [['user', 'partner'], '/discover', ['member', 'partner']],
    'member and administrator' => [['user', 'admin'], '/dashboard', ['member', 'admin']],
    'partner and administrator' => [['partner', 'admin'], '/dashboard', ['partner', 'admin']],
    'all workspaces' => [['user', 'partner', 'admin'], '/dashboard', ['member', 'partner', 'admin']],
]);

test('partner workspace navigation disappears on the first render after role removal', function () {
    $admin = User::factory()->withProfile()->admin()->partner()->create();
    $admin->profile?->update(['display_name' => 'Admin partenaire']);
    $this->actingAs($admin);

    $page = visit('/dashboard')
        ->click('[data-test="workspace-switcher-trigger"]')
        ->assertPresent('[data-test="workspace-partner-link"]')
        ->keys('[data-test="workspace-partner-link"]', 'Escape')
        ->click('[data-test="admin-partners-menu-trigger"]')
        ->assertPresent('a[href="/admin/partner-statistics"]');

    $partnerRole = Role::query()->where('name', RoleName::Partner)->firstOrFail();
    $admin->roles()->detach($partnerRole);

    $page->navigate('/dashboard')
        ->click('[data-test="workspace-switcher-trigger"]')
        ->assertMissing('[data-test="workspace-partner-link"]')
        ->keys('[data-test="workspace-admin-link"]', 'Escape')
        ->click('[data-test="admin-partners-menu-trigger"]')
        ->assertPresent('a[href="/admin/partner-statistics"]')
        ->assertNoJavaScriptErrors();
});

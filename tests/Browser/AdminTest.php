<?php

use App\Enums\PartnerAnnouncementStatus;
use App\Enums\PartnerDeliveryStatus;
use App\Enums\PartnerRevisionStatus;
use App\Enums\ProductOnboardingStatus;
use App\Enums\ProductOnboardingStep;
use App\Enums\RoleName;
use App\Mail\MemberDeletedByAdminMail;
use App\Models\Avatar;
use App\Models\Interest;
use App\Models\InterestCategory;
use App\Models\InterestSetting;
use App\Models\PartnerAnnouncement;
use App\Models\PartnerAnnouncementDelivery;
use App\Models\PartnerAnnouncementMetric;
use App\Models\PartnerProfile;
use App\Models\PartnerProfileRevision;
use App\Models\ProductOnboarding;
use App\Models\ProductOnboardingSetting;
use App\Models\SeasonalTheme;
use App\Models\User;
use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

test('admin schedules activates and disables seasonal themes on mobile', function () {
    $admin = User::factory()->withProfile()->admin()->create();
    $this->actingAs($admin);

    $page = visit('/admin/seasonal-themes')->on()->mobile()
        ->assertSee('Thèmes saisonniers')
        ->assertSee('Halloween')
        ->assertSee('Noël')
        ->assertValue('#halloween-starts-at', '')
        ->assertValue('#halloween-ends-at', '')
        ->assertPresent('[data-test="seasonal-theme-halloween"]')
        ->assertPresent('[data-test="seasonal-theme-christmas"]')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();

    $page->type('#halloween-starts-at', '2026-10-01T08:00')
        ->type('#halloween-ends-at', '2026-11-01T08:00')
        ->press('Enregistrer la période Halloween')
        ->assertSee('La programmation du thème a été enregistrée.')
        ->assertNoJavaScriptErrors();

    expect(SeasonalTheme::query()->where('theme', 'halloween')->firstOrFail()->starts_at)
        ->not->toBeNull();

    $page->press('Activer Halloween manuellement')
        ->assertSee('Le thème saisonnier a été activé manuellement.')
        ->assertScript("document.documentElement.classList.contains('seasonal-halloween')", true)
        ->assertNoJavaScriptErrors();

    $page->press('Désactiver le thème manuel')
        ->assertSee('Le forçage manuel du thème a été désactivé.')
        ->assertScript("document.documentElement.classList.contains('seasonal-halloween')", false)
        ->assertNoJavaScriptErrors();
});

test('admin without partner role opens partner management pages from its submenu', function () {
    $admin = User::factory()->admin()->create(['locale' => 'en']);

    expect($admin->fresh('roles')->hasRole(RoleName::Partner))->toBeFalse();

    $this->actingAs($admin);

    visit('/dashboard')
        ->assertPresent('[data-test="admin-partners-menu-trigger"]')
        ->click('[data-test="admin-partners-menu-trigger"]')
        ->assertSeeLink('Partner profiles')
        ->assertPresent('a[href="/admin/partner-profiles"]')
        ->assertSeeLink('Partner announcements')
        ->assertPresent('a[href="/admin/partner-announcements"]')
        ->assertSeeLink('Partner statistics')
        ->assertPresent('a[href="/admin/partner-statistics"]')
        ->click('Partner statistics')
        ->assertPathIs('/admin/partner-statistics')
        ->assertPresent('[data-test="admin-partners-menu-trigger"][data-state="open"]')
        ->assertPresent('a[href="/admin/partner-statistics"][data-active="true"]')
        ->assertNoJavaScriptErrors();
});

test('partner statistics stay readable and private on a mobile screen', function () {
    $partner = User::factory()->partner()->create();
    $profile = PartnerProfile::factory()->for($partner)->published()->create();
    $announcement = PartnerAnnouncement::factory()->for($profile)->sent()->create([
        'title' => 'Bilan de la campagne amicale',
    ]);
    PartnerAnnouncementMetric::query()->create([
        'partner_announcement_id' => $announcement->id,
        'prepared_count' => 10,
        'delivered_count' => 8,
        'read_count' => 4,
        'dismissed_count' => 2,
        'unique_click_count' => 2,
        'total_click_count' => 5,
    ]);
    $recipient = User::factory()->create([
        'email' => 'browser-recipient-secret@example.test',
    ]);
    PartnerAnnouncementDelivery::factory()
        ->for($announcement, 'announcement')
        ->for($recipient)
        ->create(['status' => PartnerDeliveryStatus::Delivered]);
    $this->actingAs($partner);

    visit('/partner/statistics')->on()->mobile()
        ->assertSee('Statistiques partenaire')
        ->assertSee('Bilan de la campagne amicale')
        ->assertSee('50,0 %')
        ->assertDontSee('browser-recipient-secret@example.test')
        ->assertPresent('[data-test="partner-statistics-table"]')
        ->assertPresent('a[href="/partner/statistics"][aria-current="page"]')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
});

test('admin partner statistics expose operational counts and retry in English', function () {
    $admin = User::factory()->admin()->partner()->create(['locale' => 'en']);
    $partner = User::factory()->partner()->create();
    $profile = PartnerProfile::factory()->for($partner)->published()->create();
    $announcement = PartnerAnnouncement::factory()->for($profile)->create([
        'title' => 'Operational campaign',
        'status' => PartnerAnnouncementStatus::Sending,
    ]);
    PartnerAnnouncementMetric::query()->create([
        'partner_announcement_id' => $announcement->id,
        'prepared_count' => 3,
        'delivered_count' => 1,
        'read_count' => 1,
    ]);
    foreach ([PartnerDeliveryStatus::Pending, PartnerDeliveryStatus::Failed, PartnerDeliveryStatus::Skipped] as $status) {
        PartnerAnnouncementDelivery::factory()
            ->for($announcement, 'announcement')
            ->create(['status' => $status]);
    }
    $this->actingAs($admin);

    visit('/admin/partner-statistics')
        ->assertSee('Partner statistics')
        ->assertSee('Operational campaign')
        ->assertSee('Pending')
        ->assertSee('Failed')
        ->assertSee('Skipped')
        ->assertPresent("[data-test=\"retry-partner-announcement-{$announcement->id}\"]")
        ->assertPresent('a[href="/partner/statistics"]')
        ->assertNoJavaScriptErrors();
});

test('an admin reviews publishes orders and unpublishes partner profiles accessibly', function () {
    config()->set('filesystems.default', 's3');
    Storage::fake('s3');
    $profile = PartnerProfile::factory()->create();
    $pending = PartnerProfileRevision::factory()->for($profile)->create([
        'name_fr' => 'Partenaire navigateur',
        'name_en' => 'Browser partner',
        'description_fr' => 'Une présentation française destinée à la validation.',
        'description_en' => 'An English presentation ready for review.',
        'image_path' => 'partners/browser-partner.webp',
        'status' => PartnerRevisionStatus::PendingApproval,
        'submitted_at' => now(),
        'draft_key' => null,
    ]);
    Storage::disk('s3')->put($pending->image_path, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL8WQAAAABJRU5ErkJggg==',
    ));
    $admin = User::factory()->withProfile()->admin()->create();
    $this->actingAs($admin);

    $page = visit('/admin/partner-profiles')->on()->mobile()
        ->assertSee('Fiches partenaires')
        ->assertSee('Partenaire navigateur')
        ->assertSee('Browser partner')
        ->assertPresent('img[alt="Partenaire navigateur"]')
        ->assertPresent('[data-test="approve-partner-profile"]')
        ->assertPresent('[data-test="reject-partner-profile"]')
        ->assertNoJavaScriptErrors();

    $page->click('[data-test="approve-partner-profile"]')
        ->assertSee('La fiche partenaire a été approuvée et publiée.')
        ->assertPresent('[aria-label="Monter Partenaire navigateur"]')
        ->assertPresent('[aria-label="Descendre Partenaire navigateur"]')
        ->assertPresent('[data-test="unpublish-partner-profile"]')
        ->assertNoJavaScriptErrors();

    expect($pending->fresh()->status)->toBe(PartnerRevisionStatus::Approved)
        ->and($profile->fresh()->is_published)->toBeTrue();

    $page->click('[data-test="unpublish-partner-profile"]')
        ->assertSee('La fiche partenaire a été dépubliée.')
        ->assertSee('Aucune fiche partenaire n’est publiée.')
        ->assertNoJavaScriptErrors();

    expect($profile->fresh()->is_published)->toBeFalse();
});

test('admin configures tutorial avatars and sees member progress', function () {
    Storage::fake('local');
    [$passAvatar, $likeAvatar] = Avatar::factory()->count(2)->create();
    foreach ([$passAvatar, $likeAvatar] as $avatar) {
        Storage::disk('local')->put($avatar->image_path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL8WQAAAABJRU5ErkJggg==',
        ));
    }
    ProductOnboardingSetting::query()->create([
        'id' => ProductOnboardingSetting::SINGLETON_ID,
        'pass_avatar_id' => $passAvatar->id,
        'like_avatar_id' => $likeAvatar->id,
    ]);
    $member = User::factory()->withProfile(false)->create(['email' => 'tutorial@example.test']);
    ProductOnboarding::factory()->for($member)->create([
        'status' => ProductOnboardingStatus::InProgress,
        'step' => ProductOnboardingStep::LikeDemo,
    ]);
    $admin = User::factory()->withProfile()->admin()->create();
    $this->actingAs($admin);

    visit('/admin/onboarding')
        ->assertSee('Tutoriel produit')
        ->assertSee('Taux de complétion')
        ->assertSee('tutorial@example.test')
        ->assertSee('Carte à découvrir')
        ->assertValue('#pass_avatar_id', (string) $passAvatar->id)
        ->assertValue('#like_avatar_id', (string) $likeAvatar->id)
        ->assertValue('#pass_display_name', 'Camille')
        ->assertValue('#like_display_name', 'Alex')
        ->type('#pass_display_name', 'Camille navigateur')
        ->type('#pass_bio', 'Biographie du profil passé depuis le navigateur.')
        ->type('#pass_display_name_en', 'Browser Camille')
        ->type('#pass_bio_en', 'Browser biography for the passed profile.')
        ->type('#like_display_name', 'Alex navigateur')
        ->type('#like_bio', 'Biographie du profil découvert depuis le navigateur.')
        ->type('#like_display_name_en', 'Browser Alex')
        ->type('#like_bio_en', 'Browser biography for the discovered profile.')
        ->press('Enregistrer la configuration')
        ->assertSee('Configuration du tutoriel enregistrée.')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('product_onboarding_settings', [
        'id' => ProductOnboardingSetting::SINGLETON_ID,
        'pass_display_name' => 'Camille navigateur',
        'pass_display_name_en' => 'Browser Camille',
        'like_display_name' => 'Alex navigateur',
        'like_display_name_en' => 'Browser Alex',
    ]);

    visit('/admin/avatars')
        ->assertDisabled("[aria-label=\"Archiver {$passAvatar->name}\"]")
        ->assertDisabled("[aria-label=\"Supprimer {$likeAvatar->name}\"]")
        ->assertSee('Utilisé par le tutoriel');
});

test('the avatar catalog renders images color gradients and admin controls', function () {
    Storage::fake('local');
    $avatar = Avatar::factory()->create([
        'name' => 'Aurore',
        'image_path' => 'avatars/aurore.png',
        'primary_color' => '#7C3AED',
        'secondary_color' => '#EC4899',
        'sort_order' => 0,
    ]);
    Storage::disk('local')->put($avatar->image_path, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL8WQAAAABJRU5ErkJggg==',
    ));
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    visit('/admin/avatars')
        ->assertSee('Avatars')
        ->assertValue("#avatar-name-{$avatar->id}", 'Aurore')
        ->assertValue("#avatar-primary-{$avatar->id}", '#7c3aed')
        ->assertValue("#avatar-secondary-{$avatar->id}", '#ec4899')
        ->assertPresent('input[name="image"][type="file"]')
        ->assertPresent('input[name="primary_color"][type="color"]')
        ->assertPresent('input[name="secondary_color"][type="color"]')
        ->assertPresent('img[alt="Avatar Aurore"]')
        ->assertScript(
            "document.querySelector('[data-test=avatar-preview-{$avatar->id}]').style.backgroundImage.includes('rgb(124, 58, 237)')",
            true,
        )
        ->assertPresent('[aria-label="Archiver Aurore"]')
        ->assertPresent('[aria-label="Supprimer Aurore"]')
        ->assertNoJavaScriptErrors();
});

test('the admin dashboard renders account statistics and recent registrations', function () {
    User::factory()->create(['email' => 'recent@example.test']);
    $admin = User::factory()->withProfile()->admin()->create();
    $this->actingAs($admin);

    visit('/dashboard')
        ->assertSee('Administration')
        ->assertSee('Comptes créés')
        ->assertSee('Comptes actifs')
        ->assertSee('E-mails vérifiés')
        ->assertSee('Profils complétés')
        ->assertSee('recent@example.test')
        ->assertSee('Profil à compléter')
        ->assertNoJavaScriptErrors();
});

test('the member catalog exposes statistics and confirms immediate deletion', function () {
    Mail::fake();
    $member = User::factory()->withProfile()->create(['email' => 'member-to-delete@example.test']);
    $admin = User::factory()->withProfile()->admin()->create();
    $this->actingAs($admin);

    $page = visit('/admin/members')->on()->mobile()
        ->assertSee('Membres')
        ->assertSee('member-to-delete@example.test')
        ->assertSee('personnes bloquées')
        ->assertCount('[data-test="delete-member-trigger"]', 1)
        ->assertCount('[data-test="start-member-conversation"]', 1)
        ->assertNoJavaScriptErrors();

    $page->click('[data-test="delete-member-trigger"]')
        ->assertPresent('[data-slot="drawer-content"]')
        ->assertSee('Supprimer ce compte ?')
        ->assertSee('supprimés immédiatement')
        ->assertSee('Annuler');

    $page->click('[data-test="confirm-delete-member"]')
        ->assertSee('Le compte a été supprimé.')
        ->assertDontSee('member-to-delete@example.test');

    $this->assertDatabaseMissing('users', ['id' => $member->id]);
    Mail::assertQueued(MemberDeletedByAdminMail::class);
});

test('an admin confirms partner roles assignment and removal from the member catalog', function (int $width, int $height) {
    $member = User::factory()->withProfile()->create(['email' => 'roles@example.test']);
    $admin = User::factory()->withProfile()->admin()->create();
    $this->actingAs($admin);

    $page = visit('/admin/members')->resize($width, $height)
        ->assertSee('roles@example.test')
        ->assertCount('[data-test="manage-member-roles-trigger"]', 1)
        ->keys('[data-test="manage-member-roles-trigger"]', 'Enter')
        ->assertPresent('[role="dialog"]')
        ->assertSee('Gérer les rôles')
        ->assertSee('Administrateur (lecture seule)')
        ->assertDisabled('[data-test="confirm-member-roles"]')
        ->assertScript('document.querySelector("[data-test=confirm-member-roles]").getBoundingClientRect().height >= 44', true)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
    $page->script('async () => { await Promise.all(document.getAnimations().map(animation => animation.finished)); }');
    $page->assertNoAccessibilityIssues();

    $page->click("#member-role-partner-{$member->id}")
        ->click("#member-role-confirmed-{$member->id}")
        ->assertEnabled('[data-test="confirm-member-roles"]')
        ->click('[data-test="confirm-member-roles"]')
        ->assertSee('Les rôles ont été mis à jour.')
        ->assertNoJavaScriptErrors();

    expect($member->fresh('roles')->hasRole('partner'))->toBeTrue();

    $page->click('[data-test="manage-member-roles-trigger"]')
        ->click("#member-role-partner-{$member->id}")
        ->click("#member-role-confirmed-{$member->id}")
        ->click('[data-test="confirm-member-roles"]')
        ->assertSee('Les rôles ont été mis à jour.')
        ->assertNoJavaScriptErrors();

    expect($member->fresh('roles')->hasRole('partner'))->toBeFalse();
    $page->assertNotPresent('[role="dialog"]')
        ->assertScript('document.activeElement?.matches("[data-test=manage-member-roles-trigger]")', true);
})->with([[320, 700], [1440, 900]]);

test('an admin starts a classic private conversation and sees the match dialog', function () {
    $member = User::factory()->withProfile()->create(['email' => 'conversation@example.test']);
    $admin = User::factory()->withProfile()->admin()->create();
    $this->actingAs($admin);

    visit('/admin/members')
        ->click('[data-test="start-member-conversation"]')
        ->assertPresent('[data-test="match-celebration-layer"]')
        ->assertPresent('[data-test="open-match-conversation"]')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('matches', [
        'user_low_id' => min($admin->id, $member->id),
        'user_high_id' => max($admin->id, $member->id),
    ]);
    $this->assertDatabaseCount('conversations', 1);
    $this->assertDatabaseCount('swipes', 0);
});

test('the catalog shows state history limit ordering and deletion boundaries', function () {
    InterestSetting::current()->update(['max_selections' => 5]);
    $archived = Interest::factory()->create([
        'name' => 'Chill',
        'is_active' => false,
        'sort_order' => 0,
    ]);
    $used = Interest::factory()->create([
        'name' => 'Spectacles',
        'is_active' => true,
        'sort_order' => 1,
    ]);
    Interest::factory()->create([
        'name' => 'Parades',
        'is_active' => true,
        'sort_order' => 2,
    ]);
    $member = User::factory()->withProfile()->create();
    $member->profile?->interestHistory()->attach($archived, ['is_selected' => false]);
    $member->profile?->interestHistory()->attach($used, ['is_selected' => true]);
    $admin = User::factory()->withProfile()->admin()->create();
    $this->actingAs($admin);

    visit('/admin/interests')
        ->assertValue('input[name="max_selections"]', '5')
        ->assertSee('Archivé')
        ->assertSee('1 profil dans l’historique')
        ->assertScript(
            "document.querySelector('[aria-label=\"Monter Chill\"]').disabled",
            true,
        )
        ->assertScript(
            "document.querySelector('[aria-label=\"Descendre Parades\"]').disabled",
            true,
        )
        ->assertScript(
            "document.querySelector('[aria-label=\"Descendre Chill\"]').disabled",
            false,
        )
        ->assertScript(
            "document.querySelector('[aria-label=\"Monter Spectacles\"]').disabled",
            false,
        )
        ->assertScript(
            "document.querySelector('[aria-label=\"Supprimer Spectacles\"]').disabled",
            true,
        )
        ->assertSee('Cet univers doit être archivé avant de pouvoir être supprimé.')
        ->assertScript(
            "document.querySelector('[aria-label=\"Supprimer Chill\"]').disabled",
            false,
        );
});

test('catalog controls expose generated forms and compact accessible layout', function () {
    $interest = Interest::factory()->create([
        'name' => 'Chill',
        'sort_order' => 0,
    ]);
    $admin = User::factory()->withProfile()->admin()->create();
    $this->actingAs($admin);

    visit('/admin/interests')
        ->assertPresent('[data-test="catalog-controls"]')
        ->assertPresent('[data-test="create-interest-form"]')
        ->assertPresent("input[aria-label='Nom de l’univers Chill']")
        ->assertScript(
            "document.querySelector('[data-test=\"catalog-controls\"] input[name=max_selections]') !== null",
            true,
        )
        ->assertScript(
            "document.querySelector('[data-test=\"catalog-controls\"] [aria-labelledby=interest-catalog-title]') === null",
            true,
        )
        ->assertScript(
            "document.querySelector('#interest-name-{$interest->id}').closest('form').action.endsWith('/admin/interests/{$interest->id}?_method=PUT')",
            true,
        )
        ->assertScript(
            "document.querySelector('input[name=max_selections]').closest('form').action.endsWith('/admin/interest-setting?_method=PATCH')",
            true,
        )
        ->assertScript(
            "document.querySelector('input[name=is_active][value=\"0\"]') === null",
            true,
        )
        ->assertScript(
            "document.querySelector('[aria-labelledby=interest-catalog-title] [class~=\"py-0\"]') !== null",
            true,
        )
        ->assertScript(
            "document.querySelector('[aria-labelledby=interest-catalog-title] [class~=\"p-3\"]') !== null",
            true,
        );
});

test('catalog actions stay aligned with their inputs and reserve validation space', function () {
    Interest::factory()->create(['name' => 'Chill']);
    $admin = User::factory()->withProfile()->admin()->create();
    $this->actingAs($admin);

    visit('/admin/interests')
        ->assertScript(
            "document.querySelector('#new_interest_name').parentElement === document.querySelector('#new_interest_name').closest('form').querySelector('button[type=submit]').parentElement",
            true,
        )
        ->assertScript(
            "document.querySelector('#new_interest_name').closest('form').querySelector('[data-test=input-error]') === null",
            true,
        )
        ->assertScript(
            "document.querySelector('#max_selections').parentElement === document.querySelector('#max_selections').closest('form').querySelector('button[type=submit]').parentElement",
            true,
        )
        ->assertNoJavaScriptErrors();
});

test('duplicate interest validation keeps the catalog unchanged', function () {
    Interest::factory()->create(['name' => 'Chill']);
    $admin = User::factory()->withProfile()->admin()->create();
    $this->app->instance(ExceptionHandlerContract::class, new ExceptionHandler($this->app));
    $this->actingAs($admin);

    visit('/admin/interests')
        ->fill('new_interest_name', 'Chill')
        ->click('Ajouter')
        ->assertSee('Le nom a déjà été utilisé.')
        ->assertValue('new_interest_name', 'Chill');

    $this->assertDatabaseCount('interests', 1);
});

test('an admin manages interests through confirmations and generated actions', function () {
    InterestCategory::factory()->create(['name' => 'Général']);
    $interest = Interest::factory()->create([
        'name' => 'Chill',
        'sort_order' => 0,
    ]);
    $admin = User::factory()->withProfile()->admin()->create();
    $this->actingAs($admin);

    $page = visit('/admin/interests')
        ->clear('max_selections')
        ->fill('max_selections', '7')
        ->click('Enregistrer')
        ->assertSee('Limite mise à jour.')
        ->fill('new_interest_name', 'Parades')
        ->click('Ajouter')
        ->assertSee('Intérêt ajouté.');

    $this->assertDatabaseHas('interest_settings', ['max_selections' => 7]);
    $this->assertDatabaseHas('interests', ['name' => 'Parades']);

    $page->click('[aria-label="Descendre Chill"]')
        ->assertScript(
            "document.querySelector('[aria-label=\"Descendre Chill\"]').disabled",
            true,
        );

    expect($interest->fresh()?->sort_order)->toBe(1);

    $page->fill("interest-name-{$interest->id}", 'Chill renommé');
    $page->script("document.querySelector('#interest-name-{$interest->id}').closest('form').querySelector('button[type=submit]').click()");
    $page->assertSee('Intérêt modifié.');

    $this->assertDatabaseHas('interests', [
        'id' => $interest->id,
        'name' => 'Chill renommé',
    ]);

    $page->click("#archive-interest-{$interest->id}")
        ->assertSee('Archiver l’univers Chill renommé')
        ->assertSee('Son historique sera conservé.')
        ->assertSee('Annuler');
    $page->script("document.querySelector('[role=dialog] button[type=submit]').click()");
    $page->assertSee('Intérêt archivé.');

    $this->assertDatabaseHas('interests', [
        'id' => $interest->id,
        'is_active' => false,
    ]);

    $page->assertNotPresent('[role=dialog]')
        ->assertVisible("#reactivate-interest-{$interest->id}");

    $page->script("document.querySelector('#reactivate-interest-{$interest->id}').click()");
    $page->assertSee('Intérêt réactivé.');

    $this->assertDatabaseHas('interests', [
        'id' => $interest->id,
        'is_active' => true,
    ]);

    $page->assertVisible("#archive-interest-{$interest->id}");
    $page->script("document.querySelector('#archive-interest-{$interest->id}').click()");
    $page->script("document.querySelector('[role=dialog] button[type=submit]').click()");
    $page->assertSee('Intérêt archivé.');

    $page->assertNotPresent('[role=dialog]')
        ->assertVisible("#delete-interest-{$interest->id}");
    $page->script("document.querySelector('#delete-interest-{$interest->id}').click()");
    $page->assertSee('Supprimer l’univers Chill renommé')
        ->assertSee('Cette action est définitive.')
        ->assertSee('Annuler');
    $page->script("document.querySelector('[role=dialog] button[type=submit]').click()");
    $page->assertSee('Intérêt supprimé.');

    $this->assertDatabaseMissing('interests', ['id' => $interest->id]);
});

test('a catalog move disables its control and preserves the scroll position', function () {
    $interests = collect(range(0, 11))->map(fn (int $index): Interest => Interest::factory()->create([
        'name' => "Intérêt {$index}",
        'sort_order' => $index,
    ]));
    $interest = $interests->get(5);
    expect($interest)->toBeInstanceOf(Interest::class);

    $admin = User::factory()->withProfile()->admin()->create();
    $this->actingAs($admin);

    $page = visit('/admin/interests')->assertValue("#interest-name-{$interest->id}", $interest->name);
    $page->script(<<<JS
        window.__realAdminXhrSend = XMLHttpRequest.prototype.send;
        window.__releaseAdminRequest = null;
        XMLHttpRequest.prototype.send = function (body) {
            if (String(this.responseURL || this.__browserTestUrl || '').includes('/admin/interests/{$interest->id}/move')) {
                window.__releaseAdminRequest = () => window.__realAdminXhrSend.call(this, body);

                return;
            }

            return window.__realAdminXhrSend.call(this, body);
        };
        window.__realAdminXhrOpen = XMLHttpRequest.prototype.open;
        XMLHttpRequest.prototype.open = function (method, url, ...rest) {
            this.__browserTestUrl = String(url);

            return window.__realAdminXhrOpen.call(this, method, url, ...rest);
        };
        true;
    JS);
    $page->script('window.scrollTo(0, document.body.scrollHeight); true;');
    $scrollY = $page->script('window.scrollY');

    $page->script("document.querySelector('[aria-label=\"Descendre {$interest->name}\"]').click()");
    $page->assertScript('window.__releaseAdminRequest !== null', true)
        ->assertDisabled("[aria-label=\"Descendre {$interest->name}\"]");
    $page->script('window.__releaseAdminRequest(); true;');
    $page->assertEnabled("[aria-label=\"Descendre {$interest->name}\"]")
        ->assertScript("Math.abs(window.scrollY - {$scrollY}) <= 1", true);

    expect($interest->fresh()?->sort_order)->toBe(6);
});

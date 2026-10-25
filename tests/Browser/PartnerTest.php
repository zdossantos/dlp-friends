<?php

use App\Actions\DeliverPartnerAnnouncement;
use App\Actions\FinalizePartnerAnnouncement;
use App\Actions\PreparePartnerAnnouncementAudience;
use App\Enums\PartnerAnnouncementStatus;
use App\Enums\PartnerRevisionStatus;
use App\Jobs\DeliverPartnerAnnouncement as DeliverPartnerAnnouncementJob;
use App\Jobs\PreparePartnerAnnouncementAudience as PreparePartnerAnnouncementAudienceJob;
use App\Models\PartnerAnnouncement;
use App\Models\PartnerProfile;
use App\Models\PartnerSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    config()->set('filesystems.default', 's3');
    Storage::fake('s3');
});

test('partner announcement histories remain reachable across pages', function (string $path, bool $administrator) {
    $partner = User::factory()->partnerOnly()->create();
    $profile = PartnerProfile::factory()->for($partner)->published()->create();
    $first = PartnerAnnouncement::factory()->for($profile)->create(['title' => 'Oldest history item']);
    PartnerAnnouncement::factory()->count(19)->for($profile)->create();
    PartnerAnnouncement::factory()->for($profile)->create(['title' => 'Newest history item']);
    $this->actingAs($administrator ? User::factory()->admin()->create() : $partner);

    $page = visit($path)->resize(320, 700)
        ->assertSee('Newest history item')->assertDontSee($first->title)
        ->click('[data-test="announcements-next"]')
        ->assertSee($first->title)->assertDontSee('Newest history item')
        ->assertMissing('[data-test="announcements-next"]')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->click('[data-test="announcements-previous"]')
        ->assertSee('Newest history item')->assertDontSee($first->title)
        ->assertNoJavaScriptErrors();
    $page->script('async () => { await Promise.all(document.getAnimations().map(animation => animation.finished)); }');
    $page->assertNoAccessibilityIssues();
})->with([
    ['/partner/announcements', false],
    ['/partner/statistics', false],
    ['/admin/partner-statistics', true],
]);

test('a partner saves a bilingual image profile and an admin publishes it', function (int $width, int $height, string $theme) {
    $partner = User::factory()->partnerOnly()->create();
    $admin = User::factory()->admin()->create();
    $image = UploadedFile::fake()->image('partner.png', 800, 450);
    $this->actingAs($partner);
    // Pest Browser's Laravel server does not parse multipart uploads yet.
    // Exercise the real upload endpoint before editing that draft in Chromium.
    $this->put(route('partner.profile.update'), [
        'name_fr' => 'Ancien nom',
        'name_en' => 'Old name',
        'description_fr' => 'Ancienne description',
        'description_en' => 'Old description',
        'image' => $image,
    ])->assertRedirect(route('partner.profile.edit'));
    $this->actingAs($partner->fresh());

    $page = visit('/partner/profile')->resize($width, $height);
    $page->script("localStorage.setItem('appearance', '{$theme}')");
    $page->navigate('/partner/profile')
        ->assertSee('Votre profil partenaire')
        ->type('#name_fr', 'Atelier des amis')
        ->type('#name_en', 'Friends workshop')
        ->type('#description_fr', 'Des idées pour partager une journée entre amis.')
        ->type('#description_en', 'Ideas for sharing a day with friends.')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->press('Enregistrer le brouillon')
        ->assertSee('Le brouillon de votre profil partenaire a été enregistré.')
        ->press('Soumettre à la modération')
        ->assertSee('Votre profil partenaire a été envoyé à la modération.')
        ->script('async () => { await Promise.all(document.getAnimations().map(animation => animation.finished)); }');
    $page->assertNoAccessibilityIssues()
        ->assertNoJavaScriptErrors();

    $profile = $partner->partnerProfile()->sole();
    expect($profile->revisions()->sole()->status)->toBe(PartnerRevisionStatus::PendingApproval);
    $this->actingAs($admin);
    $adminPage = visit('/admin/partner-profiles')->resize($width, $height)
        ->assertSee('Friends workshop')
        ->keys('[data-test="approve-partner-profile"]', 'Enter')
        ->assertSee('La fiche partenaire a été approuvée et publiée.')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
    $adminPage->script('async () => { await Promise.all(document.getAnimations().map(animation => animation.finished)); }');
    $adminPage->assertNoAccessibilityIssues();
    expect($profile->fresh()->is_published)->toBeTrue();
})->with([[320, 700, 'dark'], [1440, 900, 'light']]);

test('a partner authors an announcement and admin approves sends and checks aggregate statistics', function () {
    Queue::fake();
    $partner = User::factory()->partnerOnly()->create();
    PartnerProfile::factory()->for($partner)->published()->create();
    $admin = User::factory()->admin()->create();
    $admin->partnerNotificationPreference()->create(['enabled' => false]);
    $recipient = User::factory()->withProfile()->create();
    $recipient->partnerNotificationPreference()->create(['enabled' => true]);
    $this->actingAs($partner);

    visit('/partner/announcements')->resize(320, 700)
        ->click('Créer une annonce')
        ->type('#title', 'Une journée entre amis')
        ->type('#content', 'Découvrez nos idées pour votre prochaine visite amicale.')
        ->type('#destination_url', 'https://example.org/friends')
        ->press('Enregistrer le brouillon')
        ->assertSee('Le brouillon de l’annonce a été enregistré.')
        ->press('Soumettre')
        ->assertSee('L’annonce a été soumise à la modération.')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoAccessibilityIssues();

    $announcement = PartnerAnnouncement::query()->sole();
    $this->actingAs($admin);
    $page = visit('/admin/partner-announcements')->resize(1440, 900)
        ->keys('[data-test="approve-partner-announcement"]', 'Enter')
        ->assertSee(__('administration.partner_announcements.approved'))
        ->assertPathIs('/admin/partner-statistics');
    expect($announcement->fresh()->status)->toBe(PartnerAnnouncementStatus::Sending);

    // Execute the real worker actions; the browser test transaction defers queued after-commit jobs.
    app(PreparePartnerAnnouncementAudience::class)->handle($announcement->fresh());
    app(DeliverPartnerAnnouncement::class)->handle($announcement->deliveries()->sole());
    app(FinalizePartnerAnnouncement::class)->handle($announcement->fresh());
    $page->navigate('/admin/partner-statistics')
        ->assertSee('Envoyée')
        ->assertNotPresent('[data-test="dispatch-partner-announcement-'.$announcement->id.'"]')
        ->assertDontSee($recipient->email)
        ->assertNoJavaScriptErrors();
    expect($announcement->metric()->sole()->delivered_count)->toBe(1);

    $this->actingAs($partner);
    visit('/partner/statistics')->resize(320, 700)
        ->assertSee('Une journée entre amis')
        ->assertAttribute('[data-test="partner-statistics-table"]', 'data-layout', 'cards')
        ->assertPresent('[data-test="partner-statistics-card"]')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertDontSee($recipient->email)
        ->assertNoAccessibilityIssues();
});

test('partner statistics use cards without overflowing the mobile document', function () {
    $partner = User::factory()->partnerOnly()->create();
    $profile = PartnerProfile::factory()->for($partner)->published()->create();
    PartnerAnnouncement::factory()->for($profile)->create(['title' => 'Résultats accessibles']);
    $this->actingAs($partner);
    visit('/partner/statistics')->resize(320, 700)
        ->assertSee('Résultats accessibles')
        ->assertAttribute('[data-test="partner-statistics-table"]', 'data-layout', 'cards')
        ->assertPresent('[data-test="partner-statistics-card"]')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
});

test('admin partner dispatch reports translated validation errors without starting another send', function (string $url, string $message) {
    Queue::fake();
    $profile = PartnerProfile::factory()->published()->create();
    $announcement = PartnerAnnouncement::factory()->for($profile)->create([
        'status' => PartnerAnnouncementStatus::Approved,
        'destination_url' => $url,
    ]);
    $this->actingAs(User::factory()->admin()->create());

    visit('/admin/partner-statistics')->resize(320, 700)
        ->keys('[data-test="dispatch-partner-announcement-'.$announcement->id.'"]', 'Enter')
        ->assertSee(__($message))
        ->assertAttribute('[data-test="dispatch-partner-announcement-'.$announcement->id.'"]', 'aria-describedby', 'dispatch-error-'.$announcement->id)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
    expect($announcement->fresh()->status)->toBe(PartnerAnnouncementStatus::Approved);
    Queue::assertNothingPushed();
})->with([
    ['https://127.0.0.1/friends', 'partners.announcements.errors.unsafe_url'],
]);

test('partner and admin see the exact Paris cooldown deadline before acting', function () {
    Queue::fake();
    $partner = User::factory()->partner()->create();
    $profile = PartnerProfile::factory()->for($partner)->published()->create();
    PartnerSetting::current()->update(['cooldown_days' => 30]);
    $nextAvailableAt = now()->addDays(20)->tz('Europe/Paris');
    $partnerMessage = 'Vous pourrez soumettre une nouvelle annonce le '
        .$nextAvailableAt->locale('fr')->isoFormat('dddd D MMMM YYYY [à] HH:mm').'.';
    $adminMessage = 'Cette annonce pourra être envoyée le '
        .$nextAvailableAt->locale('fr')->isoFormat('dddd D MMMM YYYY [à] HH:mm').'.';
    PartnerAnnouncement::factory()->for($profile)->sent()->create([
        'sending_started_at' => now()->subDays(10),
        'sent_at' => now()->subDays(10),
    ]);
    $approved = PartnerAnnouncement::factory()->for($profile)->approved()->create();
    $draft = PartnerAnnouncement::factory()->for($profile)->create();

    $this->actingAs($partner);
    visit('/partner/announcements')->resize(320, 700)
        ->assertScript('document.body.innerText.includes('.json_encode($partnerMessage).')', true)
        ->assertDisabled('[data-test="partner-announcement-cooldown-submit-'.$draft->id.'"]')
        ->assertNoJavaScriptErrors();

    $this->actingAs(User::factory()->admin()->create());
    visit('/admin/partner-statistics')->resize(320, 700)
        ->assertScript('document.body.innerText.includes('.json_encode($adminMessage).')', true)
        ->assertDisabled('[data-test="dispatch-partner-announcement-'.$approved->id.'"]')
        ->assertNoJavaScriptErrors();

    expect($draft->fresh()->status)->toBe(PartnerAnnouncementStatus::Draft)
        ->and($approved->fresh()->status)->toBe(PartnerAnnouncementStatus::Approved);
    Queue::assertNothingPushed();
});

test('a partner previews the selected profile image before saving', function () {
    $this->actingAs(User::factory()->partnerOnly()->create());
    $image = UploadedFile::fake()->image('preview.png', 800, 450);
    visit('/partner/profile')->resize(320, 700)
        ->attach('#image', $image->getPathname())
        ->assertScript('document.querySelector("img[src^=blob]")?.naturalWidth', 800)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
});

test('a member reads a full partner announcement without following its link', function (int $width, int $height, string $locale, string $theme) {
    Queue::fake();
    $member = User::factory()->withProfile()->create(['locale' => $locale]);
    $content = "<strong>Message partenaire reçu</strong>\n".str_repeat("Une idée pour ta prochaine visite entre amis.\n", 60).'Dernière ligne du message.';
    $announcement = PartnerAnnouncement::factory()->create([
        'status' => PartnerAnnouncementStatus::Sending,
        'title' => 'Découvrir les idées de notre partenaire',
        'content' => $content,
        'destination_url' => 'https://example.org/friends',
    ]);
    app(PreparePartnerAnnouncementAudience::class)->handle($announcement);
    $delivery = $announcement->deliveries()->where('user_id', $member->id)->sole();
    app(DeliverPartnerAnnouncement::class)->handle($delivery);
    $notification = $member->notifications()->sole();
    $announcement->updateQuietly(['content' => 'Contenu modifié après réception']);
    $this->actingAs($member);

    $page = visit('/notifications')->resize($width, $height);
    $page->script("localStorage.setItem('appearance', '{$theme}')");
    $page->navigate('/notifications')
        ->keys('[data-test="notification-'.$notification->id.'"]', 'Enter')
        ->assertPathIs('/notifications')
        ->assertPresent('[data-test="partner-announcement-panel"]')
        ->assertAttribute('[data-test="partner-announcement-panel"]', 'data-panel-mode', $width < 640 ? 'drawer' : 'dialog')
        ->assertSee('Dernière ligne du message.')
        ->assertDontSee('Contenu modifié après réception')
        ->assertScript('document.querySelector("[data-test=partner-announcement-content]").textContent.trim()', $content)
        ->assertMissing('[data-test="partner-announcement-content"] strong')
        ->assertAttribute('[data-test="partner-announcement-link"]', 'href', route('partner-announcements.click', $delivery->click_token, absolute: false))
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertScript(<<<'JS'
            (() => {
                const panel = document.querySelector('[data-test=partner-announcement-panel]');
                const body = document.querySelector('[data-test=partner-announcement-content]').parentElement;
                return body.scrollHeight > body.clientHeight
                    && body.scrollWidth <= body.clientWidth
                    && panel.getBoundingClientRect().bottom <= window.innerHeight + 1;
            })()
            JS, true);
    $page->script('async () => { await Promise.all(document.getAnimations().map(animation => animation.finished)); }');
    $page->assertNoAccessibilityIssues()->assertNoJavaScriptErrors();

    expect($notification->fresh()->read_at)->not->toBeNull()
        ->and($delivery->fresh()->read_at)->not->toBeNull()
        ->and($announcement->metric()->sole()->read_count)->toBe(1)
        ->and($announcement->metric()->sole()->total_click_count)->toBe(0)
        ->and($delivery->fresh()->first_clicked_at)->toBeNull();

    $page->keys('[data-test="partner-announcement-close"]', 'Enter')
        ->assertMissing('[data-test="partner-announcement-panel"]')
        ->assertScript('document.activeElement.dataset.test', 'notification-'.$notification->id)
        ->keys('[data-test="notification-'.$notification->id.'"]', 'Enter')
        ->assertPresent('[data-test="partner-announcement-panel"]');
    $page->keys('[data-test="partner-announcement-close"]', 'Escape')
        ->assertMissing('[data-test="partner-announcement-panel"]');

    expect($announcement->metric()->sole()->read_count)->toBe(1)
        ->and($delivery->fresh()->click_count)->toBe(0);

    // Follow the real opaque link without loading the third-party site in Chromium.
    $this->get(route('partner-announcements.click', $delivery->click_token))
        ->assertRedirect('https://example.org/friends');
    expect($announcement->metric()->sole()->unique_click_count)->toBe(1)
        ->and($announcement->metric()->sole()->total_click_count)->toBe(1);
})->with([
    'mobile French dark' => [320, 700, 'fr', 'dark'],
    'desktop English light' => [1440, 900, 'en', 'light'],
]);

test('a partner announcement stays open when reading removes it from the unread filter', function () {
    Queue::fake();
    $member = User::factory()->withProfile()->create();
    $announcement = PartnerAnnouncement::factory()->create([
        'status' => PartnerAnnouncementStatus::Sending,
        'content' => 'Le message reste consultable après sa lecture.',
        'destination_url' => 'https://example.org/friends',
    ]);
    app(PreparePartnerAnnouncementAudience::class)->handle($announcement);
    $delivery = $announcement->deliveries()->where('user_id', $member->id)->sole();
    app(DeliverPartnerAnnouncement::class)->handle($delivery);
    $notification = $member->notifications()->sole();
    $this->actingAs($member);

    visit('/notifications?category=partners&unread=1')->resize(320, 700)
        ->click('[data-test="notification-'.$notification->id.'"]')
        ->assertPresent('[data-test="partner-announcement-panel"]')
        ->assertMissing('[data-test="notification-'.$notification->id.'"]')
        ->assertSee('Le message reste consultable après sa lecture.')
        ->keys('[data-test="partner-announcement-close"]', 'Enter')
        ->assertMissing('[data-test="partner-announcement-panel"]')
        ->assertScript('document.activeElement.dataset.test', 'notification-heading')
        ->assertNoJavaScriptErrors();

    expect($announcement->metric()->sole()->read_count)->toBe(1)
        ->and($delivery->fresh()->click_count)->toBe(0);
});

test('a member receives partner announcements by default reads dismisses and can opt out', function () {
    Queue::fake();
    $member = User::factory()->withProfile()->create();
    $this->actingAs($member);
    $page = visit('/settings/notifications')->resize(320, 700)
        ->assertScript('document.querySelector("[data-test=save-notification-preferences]").getBoundingClientRect().height >= 44', true)
        ->assertAttribute('[data-test="partner-announcements-switch"]', 'aria-checked', 'true')
        ->keys('[data-test="partner-announcements-switch"]', 'Space')
        ->press('[data-test="save-notification-preferences"]')
        ->assertAttribute('[data-test="partner-announcements-switch"]', 'aria-checked', 'false')
        ->keys('[data-test="partner-announcements-switch"]', 'Space')
        ->press('[data-test="save-notification-preferences"]')
        ->assertAttribute('[data-test="partner-announcements-switch"]', 'aria-checked', 'true');
    expect($member->partnerNotificationPreference()->sole()->enabled)->toBeTrue();

    $announcement = PartnerAnnouncement::factory()->create([
        'status' => PartnerAnnouncementStatus::Sending,
        'title' => 'Invitation partenaire navigateur',
        'content' => '<strong>Avantage partenaire figé</strong>',
        'destination_url' => 'https://example.org/friends',
    ]);
    app(PreparePartnerAnnouncementAudience::class)->handle($announcement);
    $delivery = $announcement->deliveries()->where('user_id', $member->id)->sole();
    app(DeliverPartnerAnnouncement::class)->handle($delivery);
    $notification = $member->notifications()->sole();

    $page->navigate('/notifications')
        ->click('[data-test="notification-filter-partners"]')
        ->assertSee('Invitation partenaire navigateur')
        ->assertScript('document.querySelector("[data-test=notification-title]").textContent.trim()', 'Invitation partenaire navigateur')
        ->assertScript('document.querySelector("[data-test=notification-content]").textContent.trim()', '<strong>Avantage partenaire figé</strong>')
        ->assertScript('document.querySelector("[data-test=notification-content] strong") === null', true)
        ->assertScript('document.querySelector("[data-test=notification-content]").classList.contains("line-clamp-3")', false)
        ->press(__('notifications.actions.mark_all_read'))
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
    $page->script('async () => { await Promise.all(document.getAnimations().map(animation => animation.finished)); }');
    $page->assertNoAccessibilityIssues();
    expect($delivery->fresh()->read_at)->not->toBeNull();
    $page->script('window.confirm = () => false');
    $page->keys('[data-test="notification-delete-'.$notification->id.'"]', 'Enter')
        ->assertSee('Invitation partenaire navigateur');
    expect($notification->fresh())->not->toBeNull();
    $page->script('window.confirm = () => true');
    $page->keys('[data-test="notification-delete-'.$notification->id.'"]', 'Enter')
        ->assertDontSee('Invitation partenaire navigateur')
        ->assertNoJavaScriptErrors();
    expect($delivery->fresh()->dismissed_at)->not->toBeNull()
        ->and($announcement->metric()->sole()->dismissed_count)->toBe(1);
    $page->navigate('/settings/notifications')
        ->keys('[data-test="partner-announcements-switch"]', 'Space')
        ->press('[data-test="save-notification-preferences"]')
        ->assertAttribute('[data-test="partner-announcements-switch"]', 'aria-checked', 'false');
});

test('a rejected partner announcement can be corrected and resubmitted without sending it', function () {
    Queue::fake();
    $partner = User::factory()->partnerOnly()->create();
    $profile = PartnerProfile::factory()->for($partner)->published()->create();
    $announcement = PartnerAnnouncement::factory()->for($profile)->pendingApproval()->create(['destination_url' => 'https://example.org/friends']);
    $this->actingAs(User::factory()->admin()->create());
    visit('/admin/partner-announcements')->fill('#announcement-reason-'.$announcement->id, 'Préciser le programme proposé')
        ->click('@reject-partner-announcement')->assertSee('L’annonce partenaire a été refusée.');
    expect($announcement->fresh()->status)->toBe(PartnerAnnouncementStatus::Rejected);
    $this->actingAs($partner);
    visit('/partner/announcements')->assertSee('Préciser le programme proposé')
        ->press('Créer un nouveau brouillon')->assertSee('Un nouveau brouillon prérempli a été créé.')
        ->fill('#content', 'Un programme amical précisé pour la prochaine visite.')
        ->press('Enregistrer le brouillon')->assertSee('Le brouillon de l’annonce a été enregistré.')
        ->press('Soumettre')->assertSee('L’annonce a été soumise à la modération.')->assertNoJavaScriptErrors();
    $draft = $profile->announcements()->whereKeyNot($announcement->id)->sole();
    expect($announcement->fresh()->status)->toBe(PartnerAnnouncementStatus::Rejected)
        ->and($announcement->fresh()->rejection_reason)->toBe('Préciser le programme proposé')
        ->and($draft->status)->toBe(PartnerAnnouncementStatus::PendingApproval)
        ->and($draft->content)->toBe('Un programme amical précisé pour la prochaine visite.')
        ->and($draft->deliveries()->count())->toBe(0);

    Queue::assertNotPushed(PreparePartnerAnnouncementAudienceJob::class);
    Queue::assertNotPushed(DeliverPartnerAnnouncementJob::class);
});

<?php

use App\Actions\DeliverPartnerAnnouncement;
use App\Actions\FinalizePartnerAnnouncement;
use App\Actions\PreparePartnerAnnouncementAudience;
use App\Enums\PartnerAnnouncementStatus;
use App\Enums\PartnerRevisionStatus;
use App\Models\PartnerAnnouncement;
use App\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    config()->set('filesystems.default', 's3');
    Storage::fake('s3');
});

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
        ->assertAttribute('[data-test="partner-statistics-table"]', 'tabindex', '0')
        ->keys('[data-test="partner-statistics-table"]', 'ArrowRight')
        ->assertScript('document.activeElement.matches("[data-test=partner-statistics-table]:focus-visible") && parseFloat(getComputedStyle(document.activeElement).outlineWidth) >= 2', true)
        ->assertScript('document.querySelector("[data-test=partner-statistics-table]").scrollLeft > 0', true)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertDontSee($recipient->email)
        ->assertNoAccessibilityIssues();
});

test('partner statistics can be scrolled with the keyboard without overflowing the document', function () {
    $partner = User::factory()->partnerOnly()->create();
    $profile = PartnerProfile::factory()->for($partner)->published()->create();
    PartnerAnnouncement::factory()->for($profile)->create(['title' => 'Résultats accessibles']);
    $this->actingAs($partner);
    visit('/partner/statistics')->resize(320, 700)
        ->assertSee('Résultats accessibles')
        ->assertAttribute('[data-test="partner-statistics-table"]', 'tabindex', '0')
        ->keys('[data-test="partner-statistics-table"]', 'ArrowRight')
        ->assertScript('document.querySelector("[data-test=partner-statistics-table]").scrollLeft > 0', true)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
});

test('admin partner dispatch reports translated validation errors without starting another send', function (string $url, string $message) {
    Queue::fake();
    $profile = PartnerProfile::factory()->published()->create();
    PartnerAnnouncement::factory()->for($profile)->create([
        'status' => PartnerAnnouncementStatus::Sent,
        'sending_started_at' => now(),
    ]);
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
    ['https://example.org/friends', 'administration.partner_announcements.errors.cooldown'],
    ['https://127.0.0.1/friends', 'partners.announcements.errors.unsafe_url'],
]);

test('a partner previews the selected profile image before saving', function () {
    $this->actingAs(User::factory()->partnerOnly()->create());
    $image = UploadedFile::fake()->image('preview.png', 800, 450);
    visit('/partner/profile')->resize(320, 700)
        ->attach('#image', $image->getPathname())
        ->assertScript('document.querySelector("img[src^=blob]")?.naturalWidth', 800)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
});

test('opening a partner notification exposes an opaque click route that records engagement before redirecting', function () {
    Queue::fake();
    $member = User::factory()->withProfile()->create();
    $member->partnerNotificationPreference()->create(['enabled' => true]);
    $announcement = PartnerAnnouncement::factory()->create([
        'status' => PartnerAnnouncementStatus::Sending,
        'title' => 'Découvrir les idées de notre partenaire',
        'destination_url' => 'https://example.org/',
    ]);
    app(PreparePartnerAnnouncementAudience::class)->handle($announcement);
    $delivery = $announcement->deliveries()->where('user_id', $member->id)->sole();
    app(DeliverPartnerAnnouncement::class)->handle($delivery);
    $notification = $member->notifications()->sole();
    $this->actingAs($member);
    visit('/notifications')->resize(320, 700)
        ->assertSee(__('notifications.actions.open_partner_announcement'));

    // Exercise the same internal read and opaque click routes without asking
    // Chromium to load the third-party destination, keeping this browser test
    // network-independent.
    $this->patch(route('notifications.read', $notification))
        ->assertRedirect(route('partner-announcements.click', $delivery->click_token));
    $this->get(route('partner-announcements.click', $delivery->click_token))
        ->assertRedirect('https://example.org/');

    expect($delivery->fresh()->read_at)->not->toBeNull()
        ->and($delivery->fresh()->click_count)->toBe(1)
        ->and($announcement->metric()->sole()->unique_click_count)->toBe(1)
        ->and($announcement->metric()->sole()->total_click_count)->toBe(1);
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
    $page->keys('[data-test="notification-dismiss-'.$notification->id.'"]', 'Enter')
        ->assertSee('Invitation partenaire navigateur');
    expect($notification->fresh())->not->toBeNull();
    $page->script('window.confirm = () => true');
    $page->keys('[data-test="notification-dismiss-'.$notification->id.'"]', 'Enter')
        ->assertDontSee('Invitation partenaire navigateur')
        ->assertNoJavaScriptErrors();
    expect($delivery->fresh()->dismissed_at)->not->toBeNull()
        ->and($announcement->metric()->sole()->dismissed_count)->toBe(1);
    $page->navigate('/settings/notifications')
        ->keys('[data-test="partner-announcements-switch"]', 'Space')
        ->press('[data-test="save-notification-preferences"]')
        ->assertAttribute('[data-test="partner-announcements-switch"]', 'aria-checked', 'false');
});

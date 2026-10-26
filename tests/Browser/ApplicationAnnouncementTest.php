<?php

use App\Jobs\DeliverApplicationAnnouncement;
use App\Models\ApplicationAnnouncement;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

test('admin composes previews and sends a bilingual announcement with optional push', function () {
    Queue::fake();
    $admin = User::factory()->admin()->withProfile()->create(['locale' => 'fr']);
    $this->actingAs($admin);
    $page = visit('/admin/application-announcements')->resize(1280, 900)
        ->fill('#title_fr', 'Nouveautés de l’application')
        ->fill('#title_en', 'Application update')
        ->fill('#content_fr', 'Découvrez les nouvelles fonctionnalités.')
        ->fill('#content_en', 'Discover the new features.')
        ->click('label[for=is_release]')
        ->assertNotChecked('#send_push')
        ->click('label[for=send_push]')
        ->click('Prévisualiser')
        ->assertSee('Vérifier avant envoi')
        ->assertSee('Découvrez les nouvelles fonctionnalités.')
        ->assertSee('Discover the new features.')
        ->assertSee('Notification Push : oui')
        ->assertSee($admin->email)
        ->assertNoJavaScriptErrors();
    expect(ApplicationAnnouncement::query()->sole()->sent_at)->toBeNull();
    $page->click('Confirmer l’envoi')->assertSee('Envoi confirmé')->assertNoJavaScriptErrors();
    $announcement = ApplicationAnnouncement::query()->sole();
    expect($announcement->sent_at)->not->toBeNull();
    $delivery = $announcement->deliveries()->where('user_id', $admin->id)->sole();
    app()->call([new DeliverApplicationAnnouncement($delivery->id), 'handle']);
    $notification = $admin->notifications()->sole();
    $page->navigate('/notifications?category=application')->resize(390, 844)
        ->click('[data-test=notification-'.$notification->id.']')
        ->assertSee('Découvrez les nouvelles fonctionnalités.')
        ->assertAttribute('[data-test=application-announcement-link]', 'href', '/fr/nouveautes')
        ->assertScript('document.documentElement.scrollWidth <= innerWidth', true)
        ->click('[data-test=application-announcement-link]')
        ->assertPathIs('/fr/nouveautes')
        ->assertNoJavaScriptErrors();
});

test('mobile admin selects a recipient and previews plain text without sending by default', function () {
    Queue::fake();
    $admin = User::factory()->admin()->withProfile()->create(['locale' => 'en']);
    $recipient = User::factory()->withProfile()->create(['email' => 'selected@example.com']);
    $this->actingAs($admin);
    visit('/admin/application-announcements')->resize(390, 844)
        ->fill('#title_fr', 'Information')
        ->fill('#title_en', 'Information')
        ->fill('#content_fr', '<img src=x onerror=alert(1)>')
        ->fill('#content_en', '<script>window.announcementInjected=true</script>')
        ->select('#audience', 'selected')
        ->fill('#recipient-search', $recipient->email)
        ->click('button:has-text("Search")')
        ->click('label:has-text("selected@example.com")')
        ->assertSee('1 account(s) selected')
        ->click('Preview')
        ->assertSee('Review before sending')
        ->assertSee('<script>window.announcementInjected=true</script>')
        ->assertSee('Push notification: no')
        ->assertDontSee($admin->email)
        ->assertScript('window.announcementInjected === undefined', true)
        ->assertScript('document.documentElement.scrollWidth <= innerWidth', true)
        ->assertNoJavaScriptErrors();
    $announcement = ApplicationAnnouncement::query()->sole();
    expect($announcement->sent_at)->toBeNull()
        ->and($announcement->send_push)->toBeFalse()
        ->and($announcement->deliveries()->pluck('user_id')->all())->toBe([$recipient->id]);
    Queue::assertNothingPushed();
});

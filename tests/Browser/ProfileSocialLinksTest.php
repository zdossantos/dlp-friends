<?php

use App\Enums\SwipeDecision;
use App\Models\Avatar;
use App\Models\Swipe;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function prepareSocialBrowserAvatars(): void
{
    foreach (Avatar::all() as $avatar) {
        Storage::disk('local')->put($avatar->image_path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL8WQAAAABJRU5ErkJggg==',
        ));
    }
}

test('members add edit and remove social links in the existing profile journey', function () {
    $owner = User::factory()->withProfile()->create();
    prepareSocialBrowserAvatars();
    $this->actingAs($owner);
    $page = visit('/profile/edit')->on()->mobile()
        ->click('Suivant')
        ->click('[data-test="add-social-link"]')
        ->fill('#social-url-0', 'instagram.com/parkfriend')
        ->click('Suivant')->click('Suivant')->click('Enregistrer')
        ->assertSee('Ton identité')
        ->assertSee('Saisis une adresse web complète, commençant par https://.')
        ->fill('#social-url-0', 'https://www.instagram.com/parkfriend/')
        ->click('Suivant')->click('Suivant')->click('Enregistrer')
        ->assertPathIs('/profile')
        ->assertAttribute('[data-test="social-link-instagram"]', 'href', 'https://www.instagram.com/parkfriend/')
        ->assertAttribute('[data-test="social-link-instagram"]', 'target', '_blank')
        ->assertAttribute('[data-test="social-link-instagram"]', 'rel', 'noopener noreferrer')
        ->assertNoJavaScriptErrors();
    expect($owner->profile->fresh()->social_links[0]['url'])->toBe('https://www.instagram.com/parkfriend/');
    $owner->refresh();
    $page->navigate('/profile/edit')->assertNoJavaScriptErrors()->assertPresent('input[name=avatar_id]:checked')->click('Suivant')
        ->fill('#social-url-0', 'https://www.instagram.com/newfriend/')
        ->click('Suivant')->click('Suivant')->click('Enregistrer')
        ->assertAttribute('[data-test="social-link-instagram"]', 'href', 'https://www.instagram.com/newfriend/');
    $owner->refresh();
    $page->navigate('/profile/edit')->assertNoJavaScriptErrors()->assertPresent('input[name=avatar_id]:checked')->click('Suivant')->click('[data-test="remove-social-link-0"]')
        ->click('Suivant')->click('Suivant')->click('Enregistrer')
        ->assertMissing('[data-test="social-link-instagram"]');
    expect($owner->profile->fresh()->social_links)->toBe([]);
});

test('a valid URL from another site explains which social network address is needed', function () {
    $owner = User::factory()->withProfile()->create();
    $owner->profile->update(['social_links' => [['network' => 'youtube', 'url' => 'https://youtube.com/@parkfriend']]]);
    prepareSocialBrowserAvatars();
    $this->actingAs($owner);

    visit('/profile/edit')->on()->mobile()
        ->click('Suivant')
        ->fill('#social-url-0', 'https://google.fr')
        ->click('Suivant')->click('Suivant')->click('Enregistrer')
        ->assertSee('Ton identité')
        ->assertSee('Ce lien ne correspond pas à YouTube. Utilise une adresse sur youtube.com.')
        ->fill('#social-url-0', 'https://youtu.be/HdtmCENYY1A?si=shared-link')
        ->click('Suivant')->click('Suivant')->click('Enregistrer')
        ->assertPathIs('/profile')
        ->assertAttribute('[data-test="social-link-youtube"]', 'href', 'https://youtu.be/HdtmCENYY1A?si=shared-link')
        ->assertNoJavaScriptErrors();
});

test('public social bubbles are accessible and fit a 320 pixel viewport in both themes', function (string $theme) {
    $viewer = User::factory()->withProfile()->create(['locale' => 'en']);
    $owner = User::factory()->withProfile()->create();
    $owner->profile->update(['social_links_visibility' => 'members', 'social_links' => [
        ['network' => 'instagram', 'url' => 'https://instagram.com/friend'],
        ['network' => 'tiktok', 'url' => 'https://tiktok.com/@friend'],
        ['network' => 'youtube', 'url' => 'https://youtube.com/@friend'],
    ]]);
    prepareSocialBrowserAvatars();
    $this->actingAs($viewer);
    $page = visit('/members/'.$owner->id);
    $page->script("localStorage.setItem('appearance', '$theme'); document.documentElement.classList.toggle('dark', '$theme' === 'dark'); true;");
    $page->page()->setViewportSize(320, 740);
    $page->assertCount('[data-test=profile-information-sheet] [data-test^=social-link-]', 0)
        ->assertScript("(() => { const hero = document.querySelector('[data-test=profile-presentation-hero]').getBoundingClientRect(); const links = [...document.querySelectorAll('[data-test^=social-link-]')]; return links.every((link, i) => { const box = link.getBoundingClientRect(); return link.textContent.trim() === '' && box.width >= 44 && box.height >= 44 && box.left < hero.left + 24 && box.bottom <= hero.bottom && (i === 0 || box.top >= links[i - 1].getBoundingClientRect().bottom); }); })()", true)
        ->assertAttribute('[data-test="social-link-instagram"]', 'aria-label', 'Instagram — external service, new tab')
        ->assertScript('document.documentElement.scrollWidth <= 320', true)
        ->assertScript("[...document.querySelectorAll('[data-test^=social-link-]')].every(link => link.getBoundingClientRect().right <= 320)", true)
        ->assertNoJavaScriptErrors();
    $page->script("document.querySelector('[data-test=social-link-instagram]').focus(); true;");
    $page->assertScript("document.activeElement.dataset.test === 'social-link-instagram'", true);
})->with(['light', 'dark']);

test('profile edit and cookies actions share one row on a narrow screen', function () {
    $owner = User::factory()->withProfile()->admin()->create();
    $owner->profile->update(['social_links' => [['network' => 'instagram', 'url' => 'https://instagram.com/friend']]]);
    prepareSocialBrowserAvatars();
    $this->actingAs($owner);
    visit('/profile')->resize(320, 740)
        ->assertScript("(() => { const edit = document.querySelector('a[href$=\"/profile/edit\"]').getBoundingClientRect(); const cookies = document.querySelector('[data-test=profile-analytics-consent-settings]').getBoundingClientRect(); return Math.abs(edit.top - cookies.top) < 1 && edit.right <= cookies.left && cookies.right <= 320; })()", true)
        ->assertScript("(() => { const link = document.querySelector('[data-test=social-link-instagram]').getBoundingClientRect(); return [...document.querySelectorAll('[data-test=profile-hero-action], [data-test=admin-profile-badge]')].every((action) => link.right <= action.getBoundingClientRect().left); })()", true)
        ->assertNoJavaScriptErrors();
});

test('passed profile details show authorized links while the list does not', function () {
    $viewer = User::factory()->withProfile()->create();
    $owner = User::factory()->withProfile()->create();
    $owner->profile->update(['social_links_visibility' => 'members', 'social_links' => [
        ['network' => 'instagram', 'url' => 'https://instagram.com/friend'],
    ]]);
    Swipe::factory()->create(['actor_user_id' => $viewer->id, 'target_user_id' => $owner->id, 'decision' => SwipeDecision::Pass]);
    prepareSocialBrowserAvatars();
    $this->actingAs($viewer);
    visit('/discover/passed')->resize(320, 740)
        ->assertMissing('[data-test="social-link-instagram"]')
        ->click('[data-test="passed-profile"]')
        ->assertAttribute('[data-test="social-link-instagram"]', 'href', 'https://instagram.com/friend')
        ->assertNoJavaScriptErrors();
});

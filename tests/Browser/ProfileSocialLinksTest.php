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
        ->assertSee('Utilise une URL HTTPS')
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
    $page->assertSee('Social links')
        ->assertAttribute('[data-test="social-link-instagram"]', 'aria-label', 'Instagram — external service, new tab')
        ->assertScript('document.documentElement.scrollWidth <= 320', true)
        ->assertScript("[...document.querySelectorAll('[data-test^=social-link-]')].every(link => link.getBoundingClientRect().right <= 320)", true)
        ->assertNoJavaScriptErrors();
    $page->script("document.querySelector('[data-test=social-link-instagram]').focus(); true;");
    $page->assertScript("document.activeElement.dataset.test === 'social-link-instagram'", true);
})->with(['light', 'dark']);

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

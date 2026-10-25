<?php

use App\Models\Avatar;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function preparePassBrowserAvatars(): void
{
    foreach (Avatar::all() as $avatar) {
        Storage::disk('local')->put($avatar->image_path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL8WQAAAABJRU5ErkJggg==',
        ));
    }
}

test('members choose change and remove pass and Cast Member from the profile form', function () {
    $owner = User::factory()->withProfile()->create();
    preparePassBrowserAvatars();
    $this->actingAs($owner);
    $page = visit('/profile/edit')->on()->mobile()->click('Suivant')
        ->select('#disneyland_pass', 'bronze_max')
        ->click('label[for=is_cast_member]')
        ->click('Suivant')->click('Suivant')->click('Enregistrer')
        ->assertPathIs('/profile')
        ->assertSee('Pass Bronze Max')->assertSee('Cast Member')
        ->assertPresent('[data-test=profile-presentation-hero] [data-test=profile-pass-card] svg')
        ->assertNoJavaScriptErrors();
    expect($owner->profile->fresh()->getRawOriginal('disneyland_pass'))->toBe('bronze_max');
    $owner->refresh();
    $page->navigate('/profile/edit')->click('Suivant')
        ->assertValue('#disneyland_pass', 'bronze_max')
        ->select('#disneyland_pass', 'gold')
        ->click('Suivant')->click('Suivant')->click('Enregistrer')
        ->assertSee('Pass Gold')->assertDontSee('Pass Bronze Max');
    $owner->refresh();
    $page->navigate('/profile/edit')->click('Suivant')
        ->select('#disneyland_pass', '')
        ->click('label[for=is_cast_member]')
        ->click('Suivant')->click('Suivant')->click('Enregistrer')
        ->assertMissing('[data-test=profile-pass-card]')
        ->assertMissing('[data-test=profile-cast-member]')
        ->assertNoJavaScriptErrors();
});

test('pass cards remain readable in the avatar area alongside profile actions and social links', function (string $locale, string $theme, int $width) {
    $owner = User::factory()->withProfile()->admin()->create(['locale' => $locale]);
    $owner->profile->update([
        'disneyland_pass' => 'bronze', 'is_cast_member' => true,
        'social_links' => [
            ['network' => 'instagram', 'url' => 'https://instagram.com/friend'],
            ['network' => 'tiktok', 'url' => 'https://tiktok.com/@friend'],
            ['network' => 'youtube', 'url' => 'https://youtube.com/@friend'],
        ],
    ]);
    preparePassBrowserAvatars();
    $this->actingAs($owner);
    $page = visit('/profile')->resize($width, 844);
    $page->script("localStorage.setItem('appearance', '$theme'); document.documentElement.classList.toggle('dark', '$theme' === 'dark'); true;");
    $page->assertSee($locale === 'fr' ? 'Pass Bronze (ancienne gamme)' : 'Bronze Pass (previous range)')
        ->assertAttribute('[data-test=profile-pass-card]', 'title', $locale === 'fr' ? 'Information déclarée par le membre, non vérifiée.' : 'Self-declared information, not verified.')
        ->assertScript("(() => { const card = document.querySelector('[data-test=profile-pass-card]').getBoundingClientRect(); const hero = document.querySelector('[data-test=profile-presentation-hero]').getBoundingClientRect(); const sheet = document.querySelector('[data-test=profile-information-sheet]').getBoundingClientRect(); return card.left >= hero.left && card.right <= hero.right && hero.right - card.right <= 13 && card.top >= hero.top && card.bottom <= sheet.top && [...document.querySelectorAll('[data-test=profile-hero-action], [data-test=admin-profile-badge], [data-test^=social-link-]')].every(node => { const box = node.getBoundingClientRect(); return card.bottom <= box.top || card.top >= box.bottom || card.right <= box.left || card.left >= box.right; }); })()", true)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->screenshot(filename: "profile-pass-{$locale}-{$theme}-{$width}")
        ->assertNoJavaScriptErrors();
})->with([
    ['fr', 'light', 320], ['en', 'dark', 320], ['fr', 'dark', 1280], ['en', 'light', 1280],
]);

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
        ->assertPresent('[data-test=profile-presentation-hero] [data-test=profile-cast-member]')
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
        ->assertPresent('[data-test=profile-presentation-hero] [data-test=profile-cast-member]')
        ->assertScript("(() => { const pass = document.querySelector('[data-test=profile-pass-card]').getBoundingClientRect(); const cast = document.querySelector('[data-test=profile-cast-member]').getBoundingClientRect(); return pass.width <= 96 && pass.height <= 52 && [...document.querySelectorAll('[data-test=profile-hero-action], [data-test=admin-profile-badge], [data-test^=social-link-]')].every(node => { const box = node.getBoundingClientRect(); return cast.bottom <= box.top || cast.top >= box.bottom || cast.right <= box.left || cast.left >= box.right; }); })()", true)
        ->assertScript("(() => { const card = document.querySelector('[data-test=profile-pass-card]'); const inset = card.firstElementChild; const outer = card.getBoundingClientRect(); const inner = inset.getBoundingClientRect(); const offset = inner.left - outer.left; return Math.abs(parseFloat(getComputedStyle(card).borderTopLeftRadius) - parseFloat(getComputedStyle(inset).borderTopLeftRadius) - offset) < 0.1 && Math.abs(offset - (inner.top - outer.top)) < 0.1 && Math.abs(offset - (outer.right - inner.right)) < 0.1 && Math.abs(offset - (outer.bottom - inner.bottom)) < 0.1; })()", true)
        ->assertAttribute('[data-test=profile-pass-card]', 'title', $locale === 'fr' ? 'Information déclarée par le membre, non vérifiée.' : 'Self-declared information, not verified.')
        ->assertScript("(() => { const card = document.querySelector('[data-test=profile-pass-card]').getBoundingClientRect(); const hero = document.querySelector('[data-test=profile-presentation-hero]').getBoundingClientRect(); const sheet = document.querySelector('[data-test=profile-information-sheet]').getBoundingClientRect(); return card.left >= hero.left && card.right <= hero.right && hero.right - card.right <= 13 && card.top >= hero.top && card.bottom <= sheet.top && [...document.querySelectorAll('[data-test=profile-hero-action], [data-test=admin-profile-badge], [data-test^=social-link-]')].every(node => { const box = node.getBoundingClientRect(); return card.bottom <= box.top || card.top >= box.bottom || card.right <= box.left || card.left >= box.right; }); })()", true)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->screenshot(filename: "profile-pass-{$locale}-{$theme}-{$width}")
        ->assertNoJavaScriptErrors();
})->with([
    ['fr', 'light', 320], ['en', 'dark', 320], ['fr', 'dark', 1280], ['en', 'light', 1280],
]);

test('discovery shows pass and Cast Member in the avatar area independently', function (?string $pass, bool $castMember) {
    $viewer = User::factory()->withProfile()->create();
    $owner = User::factory()->withProfile()->admin()->create();
    $owner->profile->update(['disneyland_pass' => $pass, 'is_cast_member' => $castMember]);
    preparePassBrowserAvatars();
    $this->actingAs($viewer);

    $page = visit('/discover')->resize(320, 844)
        ->assertPresent('[data-test=discovery-avatar-hero]');
    if ($pass !== null) {
        $page->assertPresent('[data-test=discovery-avatar-hero] [data-test=profile-pass-card] svg');
    } else {
        $page->assertMissing('[data-test=profile-pass-card]');
    }
    if ($castMember) {
        $page->assertPresent('[data-test=discovery-avatar-hero] [data-test=profile-cast-member]');
    } else {
        $page->assertMissing('[data-test=profile-cast-member]');
    }
    $page->assertScript("(() => { const hero = document.querySelector('[data-test=discovery-avatar-hero]').getBoundingClientRect(); const sheet = document.querySelector('[data-test=discovery-information-sheet]').getBoundingClientRect(); return [...document.querySelectorAll('[data-test=profile-pass-card], [data-test=profile-cast-member]')].every(node => { const box = node.getBoundingClientRect(); return box.left >= hero.left && box.right <= hero.right && box.top >= hero.top && box.bottom <= sheet.top; }); })()", true)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->screenshot(filename: 'discovery-pass-'.($pass ?? 'none').'-'.($castMember ? 'cast' : 'member'))
        ->assertNoJavaScriptErrors();
})->with([['bronze_max', true], ['gold', false], [null, true], [null, false]]);

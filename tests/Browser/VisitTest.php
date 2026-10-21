<?php

use App\Models\MemberMatch;
use App\Models\User;
use App\Models\Visit;

test('members create edit and delete a stay from the mobile agenda', function () {
    $member = User::factory()->withProfile()->create();
    $this->actingAs($member);
    $start = today('Europe/Paris')->addDay()->toDateString();
    $end = today('Europe/Paris')->addDays(3)->toDateString();
    $page = visit('/visits?view=agenda&audience=members')->resize(320, 700)
        ->assertSee('Visites')->click('[data-test="visit-create"]')
        ->fill('starts_on', $start)->fill('ends_on', $end)
        ->click('[data-test="visit-submit"]')->assertSee('Ta visite')
        ->assertNoJavaScriptErrors();
    expect(Visit::query()->where('user_id', $member->id)->count())->toBe(1);
    $page->assertScript("getComputedStyle(document.querySelector('[data-test=visit-agenda-day-".$start."]')).borderTopStyle", 'solid');
    $id = Visit::query()->sole()->id;
    $page->click('[data-test="visit-edit-'.$id.'-'.$start.'"]')->fill('ends_on', $start)
        ->click('[data-test="visit-submit"]')->assertNotPresent('[data-test="visit-form-panel"]');
    expect(Visit::query()->sole()->ends_on->toDateString())->toBe($start);
    $page->click('[data-test="visit-edit-'.$id.'-'.$start.'"]')->click('[data-test="visit-delete"]')
        ->click('[data-test="visit-confirm-submit"]')->assertDontSee('Ta visite')
        ->assertScript('document.documentElement.scrollWidth <= innerWidth', true)
        ->assertNoJavaScriptErrors();
    expect(Visit::query()->count())->toBe(0);
});

test('audience confirmation keeps the saved audience until explicit acceptance', function () {
    $member = User::factory()->withProfile()->create();
    $this->actingAs($member);
    $page = visit('/visits?view=agenda&audience=members')->click('#visit-visibility')->click('[data-test=visit-visibility-members]')
        ->assertSee('Partager toutes tes visites')
        ->assertAttribute('[data-test=visit-confirm-submit]', 'data-variant', 'destructive')
        ->click('[data-test="visit-confirm-cancel"]');
    expect($member->fresh()->visits_visibility->value)->toBe('hidden');
    $page->click('#visit-visibility')->click('[data-test=visit-visibility-members]')->click('[data-test="visit-confirm-submit"]')
        ->assertNoJavaScriptErrors();
    expect($member->fresh()->visits_visibility->value)->toBe('members');
    $page->click('#visit-visibility')->click('[data-test=visit-visibility-hidden]')->assertNoJavaScriptErrors();
    expect($member->fresh()->visits_visibility->value)->toBe('hidden');
});

test('calendar navigation and full lists preserve the selected context', function () {
    $member = User::factory()->withProfile()->create();
    $day = today('Europe/Paris')->addDay();
    Visit::factory()->for($member)->create(['starts_on' => $day, 'ends_on' => $day]);
    for ($i = 0; $i < 24; $i++) {
        $other = User::factory()->withProfile()->create(['visits_visibility' => 'members']);
        if ($i < 11) {
            MemberMatch::query()->create(['user_low_id' => $member->id, 'user_high_id' => $other->id]);
        }
        $other->profile->update(['display_name' => 'Ami '.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
        Visit::factory()->for($other)->create(['starts_on' => $day, 'ends_on' => $day]);
    }
    $this->actingAs($member);
    $page = visit('/visits?audience=members&month='.$day->format('Y-m').'&day='.$day->toDateString().'&view=calendar')
        ->resize(1440, 900)->assertPresent('[data-test="visit-calendar"]')
        ->click('[data-test="visit-day-'.$day->toDateString().'"]')
        ->assertSee('Également présents · 24')->screenshot(true, 'visits-desktop')
        ->click('[data-test="visit-all-attendees"]')
        ->assertSee('Ami 23')->click('[data-test="visit-attendees-back"]')
        ->click('#visit-audience')->click('[role=option][data-test=visit-audience-matches]')->assertSee('Également présents · 11')
        ->click('#visit-audience')->click('[role=option][data-test=visit-audience-members]')->assertSee('Également présents · 24')
        ->assertPresent('[data-test="visit-calendar"]')
        ->click('[data-test="visit-next-month"]')->click('[data-test="visit-previous-month"]')
        ->click('[data-test="visit-view-agenda"]')->assertPresent('[data-test="visit-agenda"]')
        ->assertNoJavaScriptErrors();
});

test('visits remain accessible in both languages and themes on narrow screens', function (string $locale, string $theme) {
    $this->actingAs(User::factory()->withProfile()->create(['locale' => $locale]));
    $page = visit('/visits?view=agenda&audience=members')->resize(320, 700);
    $page->script("document.documentElement.classList.toggle('dark', '".$theme."' === 'dark')");
    $page->script('async () => { await Promise.all(document.getAnimations().map(animation => animation.finished)); }');
    $page->assertPresent('[data-test="member-bottom-navigation"]')->assertSee($locale === 'fr' ? 'Ajouter une visite' : 'Add a visit')
        ->assertNoAccessibilityIssues()
        ->screenshot(true, 'visits-agenda-'.$locale.'-'.$theme)
        ->keys('[data-test="visit-view-calendar"]', 'Enter')
        ->assertPresent('[data-test="visit-calendar"]')
        ->assertScript('document.documentElement.scrollWidth <= innerWidth', true)
        ->assertNoAccessibilityIssues()
        ->screenshot(true, 'visits-calendar-'.$locale.'-'.$theme)
        ->click('[data-test="visit-create"]')->assertPresent('[data-test="visit-form-panel"]')
        ->assertNoAccessibilityIssues()
        ->keys('#starts_on', 'Escape')->assertNotPresent('[data-test="visit-form-panel"]')
        ->assertNoJavaScriptErrors();
})->with([['fr', 'light'], ['fr', 'dark'], ['en', 'light'], ['en', 'dark']]);

test('full visitor lists page beyond one hundred and search names literally', function () {
    $viewer = User::factory()->withProfile()->create();
    $day = today('Europe/Paris')->addDay();
    for ($i = 0; $i < 105; $i++) {
        $other = User::factory()->withProfile()->create(['visits_visibility' => 'members']);
        $other->profile->update(['display_name' => $i === 0 ? 'Élodie' : sprintf('Membre %03d', $i)]);
        Visit::factory()->for($other)->create(['starts_on' => $day, 'ends_on' => $day]);
    }
    $this->actingAs($viewer);
    $page = visit('/visits?audience=members&month='.$day->format('Y-m').'&day='.$day->toDateString().'&view=calendar')
        ->assertSee('99+')->click('[data-test="visit-all-attendees"]')
        ->assertSee('Membres présents · 105')->assertSee('Page 1 sur 4')
        ->click('Suivante')->assertSee('Page 2 sur 4')
        ->click('Suivante')->assertSee('Page 3 sur 4')
        ->click('Suivante')->assertSee('Page 4 sur 4')->assertSee('Membre 104')
        ->click('[data-test="visit-attendees-back"]')
        ->fill('visit-search', 'elodie')->assertSee('Élodie')->assertSee('Également présents · 1')
        ->click('Élodie')->assertSee('Retirer le filtre membre')
        ->assertNoJavaScriptErrors();
});

test('closing the attendee list restores its scrolled opener and keyboard focus', function () {
    $viewer = User::factory()->withProfile()->create();
    $other = User::factory()->withProfile()->create(['visits_visibility' => 'members']);
    $day = today('Europe/Paris')->addDay();
    Visit::factory()->for($other)->create(['starts_on' => $day, 'ends_on' => $day->copy()->endOfMonth()]);
    $this->actingAs($viewer);
    $date = $day->copy()->endOfMonth()->toDateString();
    $page = visit('/visits?view=agenda&audience=members&month='.$day->format('Y-m'))->resize(390, 700);
    $page->script("const button = document.querySelectorAll('[data-test=visit-all-attendees]'); button[button.length-1].scrollIntoView({block:'center'}); button[button.length-1].id='visit-scroll-target'; window.__visitScroll = window.scrollY;");
    $page->assertScript('window.__visitScroll > 0', true);
    $page->click('#visit-scroll-target');
    $page->assertPresent('[data-test="visit-attendees-panel"]')->click('[data-test="visit-attendees-back"]')
        ->assertScript("document.activeElement?.id === 'visit-opener-".$date."'", true)
        ->assertScript('Math.abs(window.scrollY-window.__visitScroll)<2', true)
        ->click('#visit-opener-'.$date)->assertPresent('[data-test="visit-attendees-panel"]');

    // The browser history snapshot can lag behind the opener's saved position.
    $page->script("window.addEventListener('popstate', () => { history.replaceState({...history.state, documentScrollPosition: {top: 0, left: 0}}, '') }, {once: true});");
    $page->back()->assertNotPresent('[data-test="visit-attendees-panel"]')
        ->assertScript("document.activeElement?.id === 'visit-opener-".$date."'", true)
        ->assertScript('Math.abs(window.scrollY-window.__visitScroll)<2', true)
        ->assertNoJavaScriptErrors();
});

test('explicit month navigation keeps browser history and filters', function () {
    $this->actingAs(User::factory()->withProfile()->create());
    $day = today('Europe/Paris');
    $page = visit('/visits?month='.$day->format('Y-m').'&day='.$day->toDateString().'&view=calendar&audience=matches')
        ->click('[data-test="visit-next-month"]')
        ->assertScript("document.querySelector('#visit-month').value", $day->copy()->addMonth()->format('Y-m'))
        ->back()
        ->assertPresent('[data-test="visit-calendar"]')
        ->assertScript("document.querySelector('#visit-month').value", $day->format('Y-m'))
        ->assertScript("document.querySelector('#visit-audience').textContent.trim()", 'Mes matchs');
});

test('stay forms and controlled confirmations restore focus and meet touch target sizes', function () {
    $member = User::factory()->withProfile()->create();
    $day = today('Europe/Paris')->addDay();
    $visit = Visit::factory()->for($member)->create(['starts_on' => $day, 'ends_on' => $day]);
    $this->actingAs($member);
    $page = visit('/visits?view=agenda&audience=members')->resize(320, 700)->click('[data-test="visit-create"]')
        ->keys('#starts_on', 'Escape')->assertNotPresent('[data-test="visit-form-panel"]')
        ->assertScript('document.activeElement?.dataset.test', 'visit-create');
    $opener = 'visit-edit-'.$visit->id.'-'.$day->toDateString();
    $page->click('[data-test="'.$opener.'"]')
        ->assertScript('Array.from(document.querySelectorAll("[data-test=visit-submit], [data-test=visit-delete]")).every(button => button.getBoundingClientRect().height >= 44)', true)
        ->screenshot(true, 'visits-form-corrected')
        ->click('[data-test="visit-delete"]')
        ->assertScript('Array.from(document.querySelectorAll("[data-test=visit-confirm-submit], [data-test=visit-confirm-cancel]")).every(button => button.getBoundingClientRect().height >= 44)', true)
        ->screenshot(true, 'visits-confirm-corrected')
        ->click('[data-test="visit-confirm-cancel"]')
        ->assertScript('document.activeElement?.dataset.test', 'visit-delete')
        ->keys('#starts_on', 'Escape')->assertNotPresent('[data-test="visit-form-panel"]')
        ->assertScript('document.activeElement?.dataset.test', $opener)
        ->click('#visit-visibility')->click('[data-test=visit-visibility-members]')->click('[data-test="visit-confirm-cancel"]')
        ->assertScript('document.activeElement?.id', 'visit-visibility');
});

test('filtered empty agendas explain missing results and offer recovery', function () {
    $this->actingAs(User::factory()->withProfile()->create());
    visit('/visits?view=agenda&search=Personne&audience=matches')->assertSee('Aucune visite ne correspond à tes filtres.')
        ->screenshot(true, 'visits-no-results-corrected')
        ->click('[data-test="visit-clear-filters"]')
        ->assertScript("document.querySelector('#visit-search').value", '')
        ->assertScript("document.querySelector('#visit-audience').textContent.trim()", 'Mes matchs')
        ->assertSee('Ajoute tes dates');
});

test('visit mutation buttons provide forty four pixel touch targets', function () {
    $member = User::factory()->withProfile()->create();
    $day = today('Europe/Paris')->addDay();
    $visit = Visit::factory()->for($member)->create(['starts_on' => $day, 'ends_on' => $day]);
    $this->actingAs($member);
    visit('/visits?view=agenda&audience=members')->resize(320, 700)->click('[data-test="visit-edit-'.$visit->id.'-'.$day->toDateString().'"]')
        ->assertScript('document.querySelector("[data-test=visit-submit]").getBoundingClientRect().height >= 44', true)
        ->assertScript('document.querySelector("[data-test=visit-delete]").getBoundingClientRect().height >= 44', true);
});

test('new visits start today independently of the browsed month and use application selectors', function () {
    $this->actingAs(User::factory()->withProfile()->create());
    $today = today('Europe/Paris')->toDateString();
    $page = visit('/visits?month='.today('Europe/Paris')->addMonth()->format('Y-m'))->resize(320, 700)
        ->assertPresent('[data-test="visit-calendar"]')
        ->assertAttribute('#visit-audience', 'role', 'combobox')
        ->assertAttribute('#visit-visibility', 'role', 'combobox')
        ->click('[data-test="visit-create"]')
        ->assertValue('#starts_on', $today)->assertValue('#ends_on', $today)
        ->assertScript('Array.from(document.querySelectorAll("input[type=date]")).every(input => input.getBoundingClientRect().right <= input.parentElement.getBoundingClientRect().right)', true)
        ->assertNoJavaScriptErrors();
});

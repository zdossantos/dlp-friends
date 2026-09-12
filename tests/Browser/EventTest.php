<?php

use App\Enums\EventRegistrationMode;
use App\Enums\EventRegistrationStatus;
use App\Enums\ProfileVisibility;
use App\Enums\SwipeDecision;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Swipe;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

test('manual and direct links open the same event panel over their workspace', function () {
    $organizer = eventBrowserMember('Alice');
    $event = Event::factory()->for($organizer, 'organizer')->create([
        'title' => 'Promenade du matin',
    ]);
    $this->actingAs($organizer);

    visit('/events')
        ->assertPresent('[data-test="discover-events"]')
        ->click("[data-test=\"event-link-{$event->id}\"]")
        ->assertPresent('[data-test="event-panel"]')
        ->assertPresent('[data-test="event-detail"]')
        ->assertPresent('[data-test="discover-events"]')
        ->assertPathIs("/events/{$event->id}")
        ->assertNoJavaScriptErrors();

    visit("/events/{$event->id}")
        ->assertPresent('[data-test="event-panel"]')
        ->assertPresent('[data-test="event-detail"]')
        ->assertPresent('[data-test="discover-events"]')
        ->assertNoJavaScriptErrors();

    visit("/events/{$event->id}?origin=mine")
        ->assertPresent('[data-test="event-panel"]')
        ->assertPresent('[data-test="event-detail"]')
        ->assertPresent('[data-test="mine-events"]')
        ->assertSee('Mes événements')
        ->assertNoJavaScriptErrors();
});

test('event details use the shadcn drawer on mobile without a close icon and a dialog on desktop', function () {
    $organizer = eventBrowserMember('Alice');
    $event = Event::factory()->for($organizer, 'organizer')->create();
    $this->actingAs($organizer);

    $page = visit("/events/{$event->id}")->on()->mobile()
        ->assertAttribute('[data-test="event-panel"]', 'data-panel-mode', 'drawer')
        ->assertAttribute('[data-test="event-panel"]', 'data-slot', 'drawer-content')
        ->assertMissing('[data-test="event-panel"] [data-slot="drawer-close-icon"]')
        ->assertScript("getComputedStyle(document.querySelector('[data-test=event-panel]')).overflowY", 'visible')
        ->assertNoJavaScriptErrors();

    $page->resize(1280, 800)
        ->assertAttribute('[data-test="event-panel"]', 'data-panel-mode', 'dialog')
        ->assertMissing('[data-test="event-panel"] [data-slot="dialog-close"]')
        ->assertNoJavaScriptErrors();
});

test('my events separates roles and keeps event details legible in dark theme', function () {
    $member = eventBrowserMember('Alice');
    $otherOrganizer = eventBrowserMember('Basile');
    $organized = Event::factory()->for($member, 'organizer')->create([
        'title' => 'Événement organisé',
    ]);
    $participating = Event::factory()->for($otherOrganizer, 'organizer')->create([
        'title' => 'Événement rejoint',
    ]);
    EventRegistration::factory()
        ->for($participating)
        ->for($member)
        ->accepted()
        ->create();
    $this->actingAs($member);

    $page = visit('/events/mine')
        ->assertPresent('[data-test="organized-events"]')
        ->assertPresent('[data-test="participating-events"]')
        ->assertPresent('[data-test="event-role-organizer"]')
        ->assertPresent('[data-test="event-role-participant"]')
        ->assertSee('J’organise')
        ->assertSee('Je participe');

    $page->script("localStorage.setItem('appearance', 'dark')");
    $page->navigate("/events/{$organized->id}?origin=mine")
        ->assertScript("document.documentElement.classList.contains('dark')", true)
        ->assertPresent('[data-test="event-detail-title"]')
        ->assertPresent('[data-test="event-detail-description"]')
        ->assertPresent('[data-test="event-private-location"]')
        ->assertScript(<<<'JS'
            (() => {
                const selectors = [
                    '[data-test="event-detail-title"]',
                    '[data-test="event-detail-description"]',
                    '[data-test="event-private-location"]',
                ];
                const channel = (value) => {
                    value /= 255;
                    return value <= 0.03928 ? value / 12.92 : Math.pow((value + 0.055) / 1.055, 2.4);
                };
                const luminance = (color) => {
                    const values = color.match(/[\d.]+/g).slice(0, 3).map(Number);
                    return 0.2126 * channel(values[0]) + 0.7152 * channel(values[1]) + 0.0722 * channel(values[2]);
                };
                return selectors.every((selector) => {
                    const element = document.querySelector(selector);
                    const style = getComputedStyle(element);
                    const foreground = luminance(style.color);
                    let backgroundElement = element;
                    let background = style.backgroundColor;
                    while (backgroundElement.parentElement && background === 'rgba(0, 0, 0, 0)') {
                        backgroundElement = backgroundElement.parentElement;
                        background = getComputedStyle(backgroundElement).backgroundColor;
                    }
                    const backgroundLuminance = luminance(background);
                    const lighter = Math.max(foreground, backgroundLuminance);
                    const darker = Math.min(foreground, backgroundLuminance);
                    return (lighter + 0.05) / (darker + 0.05) >= 4.5;
                });
            })()
            JS, true)
        ->assertNoJavaScriptErrors();
});

test('participant avatar stack opens the list and profiles inside the event panel', function () {
    $organizer = eventBrowserMember('Alice');
    $viewer = eventBrowserMember('Basile');
    $second = eventBrowserMember('Camille');
    $third = eventBrowserMember('Dorian');
    $event = Event::factory()->for($organizer, 'organizer')->create([
        'title' => 'Sortie à quatre',
        'capacity' => 6,
    ]);

    foreach ([$viewer, $second, $third] as $participant) {
        EventRegistration::factory()
            ->for($event)
            ->for($participant)
            ->accepted()
            ->create();
    }

    $this->actingAs($viewer);

    $page = visit('/events/mine')
        ->click("[data-test=\"event-link-{$event->id}\"]")
        ->assertCount('[data-test="participant-stack-avatar"]', 3)
        ->assertScript(<<<'JS'
            Array.from(document.querySelectorAll('[data-test="participant-stack-avatar"]')).every(
                (avatar) => getComputedStyle(avatar).backgroundImage.includes('linear-gradient'),
            )
            JS, true)
        ->assertSee('+1')
        ->click('[data-test="participant-stack-trigger"]')
        ->assertPathIs("/events/{$event->id}/participants")
        ->assertPresent('[data-test="event-panel"]')
        ->assertCount('[data-test="participant-row"]', 4)
        ->click("[data-test=\"participant-link-{$organizer->id}\"]")
        ->assertPresent('[data-test="event-panel"]')
        ->assertPresent('[data-test="event-participant-profile"]')
        ->assertPresent('[data-test="profile-presentation"]')
        ->assertPresent('[data-test="like-member"]')
        ->assertScript(<<<'JS'
            (() => {
                const back = document.querySelector('[data-test="participant-profile-back"]');
                const panel = document.querySelector('[data-test="event-panel"]');
                return back.getBoundingClientRect().left
                    < panel.getBoundingClientRect().left + panel.getBoundingClientRect().width / 2;
            })()
            JS, true);

    $page->script('history.back(); true;');
    $page->assertCount('[data-test="participant-row"]', 4);
    $page->script('history.back(); true;');
    $page->assertPresent('[data-test="event-detail"]');
    $page->script('history.back(); true;');
    $page->assertPathIs('/events/mine')
        ->assertPresent('[data-test="mine-events"]')
        ->assertMissing('[data-test="event-panel"]')
        ->assertNoJavaScriptErrors();
});

test('a reciprocal participant like keeps the event profile open', function () {
    $organizer = eventBrowserMember('Alice');
    $viewer = eventBrowserMember('Basile');
    $event = Event::factory()->for($organizer, 'organizer')->create();
    EventRegistration::factory()->for($event)->for($viewer)->accepted()->create();
    Swipe::factory()->create([
        'actor_user_id' => $organizer->id,
        'target_user_id' => $viewer->id,
        'decision' => SwipeDecision::Like,
    ]);
    $this->actingAs($viewer);

    visit("/events/{$event->id}/participants/{$organizer->id}?origin=mine")
        ->click('[data-test="like-member"]')
        ->assertPathIs("/events/{$event->id}/participants/{$organizer->id}")
        ->assertPresent('[data-test="event-participant-profile"]')
        ->assertSee('Vos univers se croisent')
        ->assertNoJavaScriptErrors();
});

test('an unavailable participant profile falls back inside the participant panel', function () {
    $organizer = eventBrowserMember('Alice');
    $viewer = eventBrowserMember('Basile');
    $event = Event::factory()->for($organizer, 'organizer')->create();
    EventRegistration::factory()->for($event)->for($viewer)->accepted()->create();
    $this->actingAs($viewer);

    $page = visit("/events/{$event->id}/participants?origin=mine");
    $organizer->profile?->update(['visibility' => ProfileVisibility::Hidden]);

    $page->click("[data-test=\"participant-link-{$organizer->id}\"]")
        ->assertPathIs("/events/{$event->id}/participants")
        ->assertPresent('[data-test="participant-list"]')
        ->assertSee('Ce profil n’est plus disponible')
        ->assertNoJavaScriptErrors();
});

test('a full event stays private in discovery but available to its organizer and accepted member', function () {
    $organizer = eventBrowserMember('Alice');
    $accepted = eventBrowserMember('Basile');
    $pending = eventBrowserMember('Camille');
    $outsider = eventBrowserMember('Dorian');
    $event = Event::factory()->for($organizer, 'organizer')->create([
        'title' => 'Complet entre amis',
        'capacity' => 2,
    ]);
    EventRegistration::factory()
        ->for($event)
        ->for($accepted)
        ->accepted()
        ->create();
    EventRegistration::factory()->for($event)->for($pending)->create();

    $this->actingAs($outsider);
    visit('/events')
        ->assertDontSee('Complet entre amis')
        ->assertNoJavaScriptErrors();

    $this->actingAs($organizer);
    visit('/events/mine')
        ->assertSee('Complet entre amis')
        ->click("[data-test=\"event-link-{$event->id}\"]")
        ->assertPresent('[data-test="event-private-location"]');

    $this->actingAs($accepted);
    visit('/events/mine')
        ->assertSee('Complet entre amis')
        ->click("[data-test=\"event-link-{$event->id}\"]")
        ->assertPresent('[data-test="participant-stack-trigger"]');

    $this->actingAs($pending);
    visit("/events/{$event->id}")
        ->assertSee('Le lieu précis et les participants sont visibles uniquement après acceptation.')
        ->assertMissing('[data-test="participant-stack-trigger"]')
        ->assertNoJavaScriptErrors();
});

test('an organizer creates an automatic event and a member joins then withdraws', function () {
    $organizer = eventBrowserMember('Alice');
    $member = eventBrowserMember('Basile');
    $this->actingAs($organizer);

    visit('/events')
        ->click('[data-test="event-create"]')
        ->assertPresent('[data-test="discover-events"]')
        ->assertPresent('[data-test="event-panel"]')
        ->fill('title', 'Matinée attractions')
        ->fill('description', 'Un moment amical entre fans.')
        ->fill('general_location', 'Disneyland Park')
        ->fill('detailed_location', 'Sous l’horloge de Main Street')
        ->fill('starts_at', now('Europe/Paris')->addDays(4)->format('Y-m-d\TH:i'))
        ->fill('capacity', '3')
        ->select('registration_mode', 'automatic')
        ->click('[data-test="event-submit"]')
        ->assertPresent('[data-test="discover-events"]')
        ->assertPresent('[data-test="event-detail"]')
        ->assertSee('Matinée attractions')
        ->assertSee('Sous l’horloge de Main Street')
        ->assertNoJavaScriptErrors();

    $event = Event::query()->where('title', 'Matinée attractions')->firstOrFail();
    $this->actingAs($member);

    $page = visit("/events/{$event->id}")->on()->mobile()
        ->assertDontSee('Sous l’horloge de Main Street')
        ->click('[data-test="event-register"]')
        ->assertSee('Sous l’horloge de Main Street')
        ->assertCount('[data-test="participant-stack-avatar"]', 2)
        ->assertPresent('[data-test="event-withdraw"]');

    $page->click('[data-test="event-withdraw"]')
        ->assertPresent('[data-test="event-confirm-dialog"]')
        ->assertAttribute('[data-test="event-confirm-dialog"]', 'data-slot', 'drawer-content')
        ->assertSee('Ton inscription ne sera plus active')
        ->click('[data-test="event-confirm-cancel"]')
        ->assertPresent('[data-test="event-withdraw"]');

    expect($event->registrations()->whereBelongsTo($member)->value('status'))
        ->toBe(EventRegistrationStatus::Accepted);

    $page->click('[data-test="event-withdraw"]')
        ->click('[data-test="event-confirm-submit"]')
        ->assertDontSee('Sous l’horloge de Main Street')
        ->assertNoJavaScriptErrors();

    expect($event->registrations()->whereBelongsTo($member)->value('status'))
        ->toBe(EventRegistrationStatus::Withdrawn);
});

test('a stale event panel explains when the last place has just been taken', function () {
    $organizer = eventBrowserMember('Alice');
    $viewer = eventBrowserMember('Basile');
    $lastMember = eventBrowserMember('Camille');
    $event = Event::factory()->for($organizer, 'organizer')->create([
        'capacity' => 2,
        'registration_mode' => EventRegistrationMode::Automatic,
    ]);
    $this->actingAs($viewer);

    $page = visit("/events/{$event->id}")
        ->assertPresent('[data-test="event-register"]');
    EventRegistration::factory()->for($event)->for($lastMember)->accepted()->create();

    $page->click('[data-test="event-register"]')
        ->assertSee('Cet événement est complet.')
        ->assertMissing('[data-test="event-register"]')
        ->assertNoJavaScriptErrors();
});

test('stale organizer requests explain a capacity conflict', function () {
    $organizer = eventBrowserMember('Alice');
    $pending = eventBrowserMember('Basile');
    $lastMember = eventBrowserMember('Camille');
    $event = Event::factory()->for($organizer, 'organizer')->create([
        'capacity' => 2,
        'registration_mode' => EventRegistrationMode::Manual,
    ]);
    $request = EventRegistration::factory()->for($event)->for($pending)->create();
    $this->actingAs($organizer);

    $page = visit("/events/{$event->id}/requests")
        ->assertSee('Basile');
    EventRegistration::factory()->for($event)->for($lastMember)->accepted()->create();

    $page->click('Accepter')
        ->assertSee('Cet événement est complet.')
        ->assertSee('En attente')
        ->assertNoJavaScriptErrors();

    expect($request->refresh()->status)->toBe(EventRegistrationStatus::Pending);
});

test('opening and closing an event preserves list scroll and restores opener focus', function () {
    $organizer = eventBrowserMember('Alice');
    $this->actingAs($organizer);
    $events = collect(range(1, 14))->map(fn (int $day) => Event::factory()
        ->for($organizer, 'organizer')
        ->create(['starts_at' => now()->addDays($day)]));
    $target = $events->last();

    $page = visit('/events/mine')->resize(390, 700);
    $page->script('new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))');
    $page->script("const opener = document.querySelector('[data-test=event-link-{$target->id}]'); opener.scrollIntoView({ block: 'center' }); const region = document.querySelector('[data-test=member-shell-content]'); window.__eventScroll = region.scrollTop;");
    $page->assertScript('window.__eventScroll > 0', true)
        ->click("[data-test=\"event-link-{$target->id}\"]")
        ->assertPresent('[data-test="event-panel"]')
        ->click('[data-slot="drawer-overlay"]');
    $page->assertPathIs('/events/mine')
        ->assertScript("document.activeElement?.dataset.test === 'event-link-{$target->id}'", true)
        ->assertScript("Math.abs(document.querySelector('[data-test=member-shell-content]').scrollTop - window.__eventScroll) < 2", true)
        ->assertNoJavaScriptErrors();
});

test('manual registration protects private data and lets the organizer accept refuse and remove', function () {
    $organizer = eventBrowserMember('Alice');
    $accepted = eventBrowserMember('Basile');
    $refused = eventBrowserMember('Camille');
    $event = Event::factory()->for($organizer, 'organizer')->create([
        'title' => 'Après-midi spectacles',
        'registration_mode' => EventRegistrationMode::Manual,
    ]);
    EventRegistration::factory()->for($event)->for($accepted)->create();
    EventRegistration::factory()->for($event)->for($refused)->create();

    $this->actingAs($accepted);
    visit("/events/{$event->id}")
        ->assertSee('Le lieu précis et les participants sont visibles uniquement après acceptation.')
        ->assertSee('En attente')
        ->assertDontSee($event->detailed_location)
        ->assertNoJavaScriptErrors();

    $this->actingAs($organizer);
    $page = visit("/events/{$event->id}")
        ->click('[data-test="event-registrations"]')
        ->assertPresent('[data-test="event-panel"]')
        ->assertSee('Basile')
        ->assertSee('Camille')
        ->click('[data-test="event-registrations-back"]')
        ->assertPresent('[data-test="event-detail"]')
        ->click('[data-test="event-registrations"]')
        ->click('Accepter');
    $page->click('Refuser')
        ->assertSee('Cette décision est définitive')
        ->click('[data-test="event-confirm-cancel"]')
        ->assertSee('Camille');

    expect($event->registrations()->whereBelongsTo($refused)->value('status'))
        ->toBe(EventRegistrationStatus::Pending);

    $page->click('Refuser')
        ->click('[data-test="event-confirm-submit"]')
        ->assertNoJavaScriptErrors();

    expect($event->registrations()->whereBelongsTo($accepted)->value('status'))
        ->toBe(EventRegistrationStatus::Accepted)
        ->and($event->registrations()->whereBelongsTo($refused)->value('status'))
        ->toBe(EventRegistrationStatus::Refused);

    $page->click('Retirer')
        ->assertSee('La personne perdra immédiatement l’accès')
        ->click('[data-test="event-confirm-submit"]')
        ->assertDontSee('Retirer');
    expect($event->registrations()->whereBelongsTo($accepted)->value('status'))
        ->toBe(EventRegistrationStatus::Removed);
});

test('event creation preserves its description after a validation error', function () {
    $member = eventBrowserMember('Alice');
    $this->actingAs($member);

    visit('/events/create')
        ->fill('title', 'Une journée entre amis')
        ->fill('description', 'Description à préserver après validation.')
        ->fill('general_location', 'Disneyland Park')
        ->fill('detailed_location', 'Sous l’horloge de Main Street')
        ->fill('starts_at', now('Europe/Paris')->subDay()->format('Y-m-d\TH:i'))
        ->fill('capacity', '4')
        ->click('[data-test="event-submit"]')
        ->assertValue('description', 'Description à préserver après validation.')
        ->assertSee('doit être une date postérieure')
        ->assertNoJavaScriptErrors();
});

test('date and location changes notify a member and cancellation remains in history', function () {
    $organizer = eventBrowserMember('Alice');
    $member = eventBrowserMember('Basile');
    $event = Event::factory()->for($organizer, 'organizer')->create([
        'title' => 'Rencontre du soir',
        'starts_at' => now()->addDays(5),
    ]);
    EventRegistration::factory()->for($event)->for($member)->accepted()->create();
    $this->actingAs($organizer);

    visit("/events/{$event->id}?origin=mine")
        ->click('[data-test="event-edit"]')
        ->assertPresent('[data-test="mine-events"]')
        ->assertPresent('[data-test="event-panel"]')
        ->fill('general_location', 'Walt Disney Studios')
        ->fill('detailed_location', 'Devant Studio 1')
        ->fill('starts_at', now('Europe/Paris')->addDays(6)->format('Y-m-d\TH:i'))
        ->click('[data-test="event-submit"]')
        ->assertPresent('[data-test="mine-events"]')
        ->assertPresent('[data-test="event-detail"]')
        ->assertSee('Walt Disney Studios')
        ->assertSee('Devant Studio 1');

    $this->actingAs($member);
    $notificationId = $member->notifications()->latest()->value('id');
    visit('/notifications')
        ->assertSee('Rencontre du soir')
        ->click("[data-test=\"notification-{$notificationId}\"]")
        ->assertPathIs("/events/{$event->id}")
        ->assertSee('Walt Disney Studios')
        ->assertNoJavaScriptErrors();

    $this->actingAs($organizer);
    $page = visit("/events/{$event->id}");
    $page->click('Annuler l’événement')
        ->assertSee('Les membres inscrits seront prévenus')
        ->click('[data-test="event-confirm-submit"]')
        ->assertSee('Annulé');

    visit('/events/mine')
        ->assertSee('Rencontre du soir')
        ->assertSee('Annulé')
        ->assertNoJavaScriptErrors();
});

function eventBrowserMember(string $displayName): User
{
    $user = User::factory()->withProfile()->create();
    $user->profile?->update(['display_name' => $displayName]);
    Storage::disk('local')->put($user->profile->avatar->image_path, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL8WQAAAABJRU5ErkJggg==',
    ));

    return $user;
}

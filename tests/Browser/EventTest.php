<?php

use App\Enums\EventRegistrationMode;
use App\Enums\EventRegistrationStatus;
use App\Models\Event;
use App\Models\EventRegistration;
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

test('an organizer creates an automatic event and a member joins then withdraws', function () {
    $organizer = eventBrowserMember('Alice');
    $member = eventBrowserMember('Basile');
    $this->actingAs($organizer);

    visit('/events/create')
        ->fill('title', 'Matinée attractions')
        ->fill('description', 'Un moment amical entre fans.')
        ->fill('general_location', 'Disneyland Park')
        ->fill('detailed_location', 'Sous l’horloge de Main Street')
        ->fill('starts_at', now('Europe/Paris')->addDays(4)->format('Y-m-d\TH:i'))
        ->fill('capacity', '3')
        ->select('registration_mode', 'automatic')
        ->click('[data-test="event-submit"]')
        ->assertSee('Matinée attractions')
        ->assertSee('Sous l’horloge de Main Street')
        ->assertNoJavaScriptErrors();

    $event = Event::query()->where('title', 'Matinée attractions')->firstOrFail();
    $this->actingAs($member);

    $page = visit("/events/{$event->id}")
        ->assertDontSee('Sous l’horloge de Main Street')
        ->click('[data-test="event-register"]')
        ->assertSee('Sous l’horloge de Main Street')
        ->assertSee('Alice')
        ->assertPresent('[data-test="event-withdraw"]');

    $page->script('window.confirm = () => true');
    $page->click('[data-test="event-withdraw"]')
        ->assertDontSee('Sous l’horloge de Main Street')
        ->assertNoJavaScriptErrors();

    expect($event->registrations()->whereBelongsTo($member)->value('status'))
        ->toBe(EventRegistrationStatus::Withdrawn);
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
        ->assertSee('Basile')
        ->assertSee('Camille')
        ->click('Accepter');
    $page->script('window.confirm = () => true');
    $page->click('Refuser')->assertNoJavaScriptErrors();

    expect($event->registrations()->whereBelongsTo($accepted)->value('status'))
        ->toBe(EventRegistrationStatus::Accepted)
        ->and($event->registrations()->whereBelongsTo($refused)->value('status'))
        ->toBe(EventRegistrationStatus::Refused);

    $page->click('Retirer')->assertDontSee('Retirer');
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

    visit("/events/{$event->id}/edit")
        ->fill('general_location', 'Walt Disney Studios')
        ->fill('detailed_location', 'Devant Studio 1')
        ->fill('starts_at', now('Europe/Paris')->addDays(6)->format('Y-m-d\TH:i'))
        ->click('[data-test="event-submit"]')
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
    $page->script('window.confirm = () => true');
    $page->click('Annuler l’événement')->assertSee('Annulé');

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

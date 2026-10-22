<?php

use App\Actions\RemoveTripParticipant;
use App\Actions\SendGroupChatMessage;
use App\Models\Avatar;
use App\Models\Trip;
use App\Models\TripRegistration;
use App\Models\User;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Avatar::created(fn (Avatar $avatar) => Storage::put($avatar->image_path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL8WQAAAABJRU5ErkJggg==')));
    $this->organizer = User::factory()->withProfile()->create();
    $this->member = User::factory()->withProfile()->create();
    $this->trip = Trip::factory()->for($this->organizer, 'organizer')->create(['audience' => 'members']);
    $this->trip->chat()->create();
    $this->registration = TripRegistration::factory()->for($this->trip)->for($this->member)->create(['status' => 'accepted']);
});

test('trip group has a single conversation, preserves draft through profile and reload, and has no bottom bar', function () {
    $this->actingAs($this->member);
    $page = visit('/trips/'.$this->trip->id.'/chat')->resize(320, 800)->assertPresent('#message-content')->assertNotPresent('@member-bottom-navigation');
    $page->fill('#message-content', 'Mon brouillon')->click('@group-chat-info')->assertSee($this->trip->meeting_point)->click('@trip-organizer')->assertPresent('@event-participant-profile')->back()->assertSee($this->trip->meeting_point)->back()->assertValue('#message-content', 'Mon brouillon');
    $page->refresh()->assertValue('#message-content', 'Mon brouillon')->assertNotPresent('@member-bottom-navigation')->assertScript("document.querySelector('[data-test=conversation-page]').getBoundingClientRect().bottom <= window.innerHeight + 1", true)->click('[aria-label="Envoyer le message"]')->assertSee('Mon brouillon')->assertValue('#message-content', '')->assertNoJavaScriptErrors();
});

test('trip group renders messages and reactions, and appears in Groups with individual unread counts', function () {
    app(SendGroupChatMessage::class)->handle($this->organizer, $this->trip->chat, 'Rendez-vous demain');
    $this->actingAs($this->member);
    $page = visit('/conversations?category=groups')->resize(320, 800)->assertPresent('@group-conversation-'.$this->trip->chat->id);
    $page->click('@group-conversation-'.$this->trip->chat->id)->assertSee('Rendez-vous demain')->resize(1280, 900)->click('@message-like')->assertPresent('[data-test="message-like"][aria-pressed="true"]')->assertNoJavaScriptErrors();
    expect($this->trip->chat->messages()->where('content', 'Rendez-vous demain')->first()->reactions()->count())->toBe(1);
});

test('cancelled trip chat stays readable without a composer', function () {
    app(SendGroupChatMessage::class)->handle($this->organizer, $this->trip->chat, 'Message conservé');
    $this->trip->update(['cancelled_at' => now()]);
    $this->actingAs($this->member);
    visit('/trips/'.$this->trip->id.'/chat')->resize(320, 800)->assertSee('Message conservé')->assertNotPresent('#message-content')->assertNotPresent('@member-bottom-navigation')->assertNoJavaScriptErrors();
});

test('two separate browser sessions exchange messages and lose private access after exclusion', function () {
    // Pest reuses one Laravel container; restore request-local auth/session state.
    Event::listen(RouteMatched::class, function () {
        Auth::forgetGuards();
        session()->flush();
    });
    $this->organizer->update(['locale' => 'fr']);
    $this->member->update(['locale' => 'fr']);
    $owner = visit('/login')->resize(1280, 900)->assertPresent('#email');
    $owner->fill('#email', $this->organizer->email)->fill('#password', 'password')->click('[data-test="login-button"]')->assertPathIs('/discover')->navigate('/trips/'.$this->trip->id.'/chat')->assertPresent('#message-content');
    $passenger = visit('/login')->resize(320, 800)->assertPresent('#email');
    $passenger->fill('#email', $this->member->email)->fill('#password', 'password')->click('[data-test="login-button"]')->assertPathIs('/discover')->navigate('/trips/'.$this->trip->id.'/chat')->assertPresent('#message-content');
    $owner->fill('#message-content', 'Message entre deux sessions')->click('[aria-label="Envoyer le message"]')->assertSee('Message entre deux sessions');
    $passenger->refresh()->assertSee('Message entre deux sessions')->fill('#message-content', 'Réponse du passager')->click('[aria-label="Envoyer le message"]')->assertSee('Réponse du passager');
    $owner->refresh()->assertSee('Réponse du passager');
    $passenger->click('@group-chat-info')->assertSee($this->trip->meeting_point);
    app(RemoveTripParticipant::class)->handle($this->organizer, $this->registration, 'Empêchement');
    $passenger->assertScript('fetch("/trips/'.$this->trip->id.'/chat", {headers:{Accept:"application/json"}}).then(response => response.status)', 403)->navigate('/conversations?category=groups')->assertNotPresent('@group-conversation-'.$this->trip->chat->id)->assertNoJavaScriptErrors();
});

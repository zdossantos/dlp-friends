<?php

namespace Tests\Feature;

use App\Enums\EventRegistrationMode;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_secondary_routes_render_the_discovery_workspace(): void
    {
        $organizer = User::factory()->withProfile()->create();
        $event = Event::factory()->for($organizer, 'organizer')->create();

        foreach ([
            [route('events.create'), 'create'],
            [route('events.show', $event), 'detail'],
            [route('events.edit', $event), 'edit'],
        ] as [$url, $kind]) {
            $this->actingAs($organizer)->get($url)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Events/Index')
                    ->where('context', 'discover')
                    ->where('panel.kind', $kind)
                    ->where('closeHref', route('events.index', absolute: false)));
        }
    }

    public function test_a_trusted_mine_origin_reconstructs_the_my_events_workspace(): void
    {
        $organizer = User::factory()->withProfile()->create();
        $event = Event::factory()->for($organizer, 'organizer')->create();

        $this->actingAs($organizer)
            ->get(route('events.show', ['event' => $event, 'origin' => 'mine']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Events/Mine')
                ->where('context', 'mine')
                ->where('panel.kind', 'detail')
                ->where('panel.event.id', $event->id)
                ->where('closeHref', route('events.mine', absolute: false)));
    }

    public function test_an_untrusted_origin_falls_back_to_the_discovery_workspace(): void
    {
        $member = User::factory()->withProfile()->create();

        $this->actingAs($member)
            ->get(route('events.create', ['origin' => 'https://example.com']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Events/Index')
                ->where('context', 'discover')
                ->where('closeHref', route('events.index', absolute: false)));
    }

    public function test_create_and_update_redirect_to_the_detail_panel_with_the_same_origin(): void
    {
        $this->travelTo('2026-09-10 10:00:00');
        $organizer = User::factory()->withProfile()->create();
        $attributes = [
            'title' => 'Une journée entre amis',
            'description' => 'Retrouvons-nous pour profiter du parc.',
            'general_location' => 'Disneyland Park',
            'detailed_location' => 'Sous l’horloge de Main Street Station',
            'starts_at' => '2026-10-10T10:30',
            'capacity' => 4,
            'registration_mode' => EventRegistrationMode::Automatic->value,
        ];

        $create = $this->actingAs($organizer)
            ->post(route('events.store', ['origin' => 'mine']), $attributes);
        $event = Event::query()->firstOrFail();

        $create->assertRedirect(route('events.show', [
            'event' => $event,
            'origin' => 'mine',
        ], absolute: false));

        $this->actingAs($organizer)
            ->patch(route('events.update', ['event' => $event, 'origin' => 'mine']), [
                ...$attributes,
                'title' => 'Titre modifié',
            ])
            ->assertRedirect(route('events.show', [
                'event' => $event,
                'origin' => 'mine',
            ], absolute: false));
    }

    public function test_cancelling_an_event_keeps_the_current_workspace_and_detail_panel(): void
    {
        $organizer = User::factory()->withProfile()->create();
        $event = Event::factory()->for($organizer, 'organizer')->create();

        $this->actingAs($organizer)
            ->patch(route('events.cancel', ['event' => $event, 'origin' => 'mine']))
            ->assertRedirect(route('events.show', [
                'event' => $event,
                'origin' => 'mine',
            ], absolute: false));
    }
}

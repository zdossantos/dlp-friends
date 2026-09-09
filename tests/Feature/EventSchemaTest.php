<?php

namespace Tests\Feature;

use App\Enums\EventRegistrationStatus;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EventSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_can_only_register_once_for_an_event(): void
    {
        $event = Event::factory()->create();
        $member = User::factory()->create();

        EventRegistration::factory()->create([
            'event_id' => $event->id,
            'user_id' => $member->id,
        ]);

        $this->expectException(QueryException::class);

        EventRegistration::factory()->create([
            'event_id' => $event->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_a_registration_rejects_a_status_outside_the_storage_enum(): void
    {
        $event = Event::factory()->create();
        $member = User::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('event_registrations')->insert([
            'event_id' => $event->id,
            'user_id' => $member->id,
            'status' => 'unknown',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_deleting_an_event_deletes_its_registrations(): void
    {
        $registration = EventRegistration::factory()->create([
            'status' => EventRegistrationStatus::Accepted,
        ]);

        $registration->event->delete();

        $this->assertDatabaseMissing('event_registrations', ['id' => $registration->id]);
    }
}

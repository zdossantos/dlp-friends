<?php

namespace Tests\Feature\Settings;

use App\Actions\RequestAccountDeletion;
use App\Enums\UserStatus;
use App\Jobs\PurgeDeletedUser;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\SocialAccount;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_deletion_is_persisted_and_blocks_social_access(): void
    {
        $user = User::factory()->withProfile()->create([
            'status' => UserStatus::PendingDeletion,
            'deletion_requested_at' => now(),
        ]);

        $this->actingAs($user)->get(route('discovery.index'))->assertForbidden();

        $this->assertInstanceOf(CarbonImmutable::class, $user->fresh()->deletion_requested_at);
    }

    public function test_confirmed_deletion_immediately_revokes_access_and_schedules_purge(): void
    {
        CarbonImmutable::setTestNow('2026-09-08 12:00:00');
        Queue::fake();
        $user = User::factory()->withProfile()->create();
        $other = User::factory()->create();
        DB::table('sessions')->insert([
            ['id' => 'member-session', 'user_id' => $user->id, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => now()->timestamp],
            ['id' => 'other-session', 'user_id' => $other->id, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => now()->timestamp],
        ]);
        SocialAccount::factory()->for($user)->create();
        $organizedEvent = Event::factory()->create(['organizer_user_id' => $user->id]);

        $this->actingAs($user)
            ->delete(route('account.destroy'), ['password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $fresh = $user->fresh();
        $this->assertGuest();
        $this->assertSame(UserStatus::PendingDeletion, $fresh->status);
        $this->assertTrue($fresh->deletion_requested_at->equalTo(now()));
        $this->assertFalse(DB::table('sessions')->where('user_id', $user->id)->exists());
        $this->assertTrue(DB::table('sessions')->where('user_id', $other->id)->exists());
        $this->assertFalse($user->socialAccounts()->exists());
        $this->assertNotNull($organizedEvent->fresh()->cancelled_at);
        Queue::assertPushed(PurgeDeletedUser::class, fn (PurgeDeletedUser $job): bool => $job->delay?->equalTo(now()->addDays(30)) === true);
    }

    public function test_repeated_deletion_request_keeps_the_original_deadline(): void
    {
        CarbonImmutable::setTestNow('2026-09-08 12:00:00');
        Queue::fake();
        $user = User::factory()->create();
        $action = app(RequestAccountDeletion::class);

        $first = $action->handle($user);
        CarbonImmutable::setTestNow(now()->addDay());
        $second = $action->handle($user->fresh());

        $this->assertTrue($first->equalTo($second));
        Queue::assertPushed(PurgeDeletedUser::class, 1);
    }

    public function test_purge_removes_event_registrations_and_database_notifications(): void
    {
        $this->travelTo('2026-10-10 12:00:00');
        $user = User::factory()->withProfile()->create([
            'status' => UserStatus::PendingDeletion,
            'deletion_requested_at' => now()->subDays(31),
        ]);
        EventRegistration::factory()->create(['user_id' => $user->id]);
        $user->notifications()->create([
            'id' => fake()->uuid(),
            'type' => 'test',
            'data' => [],
        ]);

        app(PurgeDeletedUser::class, [
            'userId' => $user->id,
            'requestedAt' => $user->deletion_requested_at->toISOString(),
        ])->handle();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('event_registrations', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $user->id]);
    }
}

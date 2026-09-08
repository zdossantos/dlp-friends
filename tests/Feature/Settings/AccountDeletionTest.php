<?php

namespace Tests\Feature\Settings;

use App\Actions\RequestAccountDeletion;
use App\Enums\UserStatus;
use App\Jobs\PurgeDeletedUser;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\UserDataExport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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
        Storage::fake('exports');
        config()->set('data-control.exports.disk', 'exports');
        $user = User::factory()->withProfile()->create();
        $other = User::factory()->create();
        DB::table('sessions')->insert([
            ['id' => 'member-session', 'user_id' => $user->id, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => now()->timestamp],
            ['id' => 'other-session', 'user_id' => $other->id, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => now()->timestamp],
        ]);
        SocialAccount::factory()->for($user)->create();
        $path = "user-data-exports/{$user->id}/ready.json";
        Storage::disk('exports')->put($path, '{}');
        UserDataExport::factory()->for($user)->create(['path' => $path]);

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
        $this->assertFalse($user->dataExports()->exists());
        Storage::disk('exports')->assertMissing($path);
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
}

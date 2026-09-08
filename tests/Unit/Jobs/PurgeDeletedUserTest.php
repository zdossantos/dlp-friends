<?php

namespace Tests\Unit\Jobs;

use App\Enums\UserStatus;
use App\Jobs\PurgeDeletedUser;
use App\Models\MemberMatch;
use App\Models\Message;
use App\Models\User;
use App\Models\UserDataExport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurgeDeletedUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_pending_account_and_linked_private_data_are_purged_idempotently(): void
    {
        CarbonImmutable::setTestNow('2026-09-08 12:00:00');
        Storage::fake('exports');
        config()->set('data-control.exports.disk', 'exports');
        $requestedAt = CarbonImmutable::now()->subDays(30);
        $user = User::factory()->withProfile()->create([
            'status' => UserStatus::PendingDeletion,
            'deletion_requested_at' => $requestedAt,
        ]);
        $other = User::factory()->create();
        [$low, $high] = collect([$user->id, $other->id])->sort()->values()->all();
        $match = MemberMatch::factory()->create(['user_low_id' => $low, 'user_high_id' => $high]);
        $conversation = $match->conversation()->create();
        Message::factory()->create(['conversation_id' => $conversation->id, 'author_user_id' => $user->id]);
        $path = "user-data-exports/{$user->id}/ready.json";
        Storage::disk('exports')->put($path, '{}');
        UserDataExport::factory()->for($user)->create(['path' => $path]);
        $job = new PurgeDeletedUser($user->id, $requestedAt->toISOString());

        $job->handle();
        $job->handle();

        $this->assertNull(User::find($user->id));
        $this->assertNotNull(User::find($other->id));
        $this->assertFalse(DB::table('messages')->where('conversation_id', $conversation->id)->exists());
        Storage::disk('exports')->assertMissing($path);
    }

    public function test_purge_guards_reject_early_active_and_mismatched_requests(): void
    {
        CarbonImmutable::setTestNow('2026-09-08 12:00:00');
        $earlyAt = CarbonImmutable::now()->subDays(29);
        $early = User::factory()->create(['status' => UserStatus::PendingDeletion, 'deletion_requested_at' => $earlyAt]);
        $activeAt = CarbonImmutable::now()->subDays(31);
        $active = User::factory()->create(['status' => UserStatus::Active, 'deletion_requested_at' => $activeAt]);
        $mismatchAt = CarbonImmutable::now()->subDays(31);
        $mismatch = User::factory()->create(['status' => UserStatus::PendingDeletion, 'deletion_requested_at' => $mismatchAt]);

        (new PurgeDeletedUser($early->id, $earlyAt->toISOString()))->handle();
        (new PurgeDeletedUser($active->id, $activeAt->toISOString()))->handle();
        (new PurgeDeletedUser($mismatch->id, $mismatchAt->addSecond()->toISOString()))->handle();

        $this->assertNotNull($early->fresh());
        $this->assertNotNull($active->fresh());
        $this->assertNotNull($mismatch->fresh());
    }
}

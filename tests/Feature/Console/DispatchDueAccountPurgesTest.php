<?php

namespace Tests\Feature\Console;

use App\Enums\UserStatus;
use App\Jobs\PurgeDeletedUser;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DispatchDueAccountPurgesTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_dispatches_only_due_pending_accounts(): void
    {
        CarbonImmutable::setTestNow('2026-09-08 12:00:00');
        Queue::fake();
        $dueAt = CarbonImmutable::now()->subDays(30);
        $due = User::factory()->create(['status' => UserStatus::PendingDeletion, 'deletion_requested_at' => $dueAt]);
        User::factory()->create(['status' => UserStatus::PendingDeletion, 'deletion_requested_at' => now()->subDays(29)]);
        User::factory()->create(['status' => UserStatus::Active, 'deletion_requested_at' => now()->subDays(31)]);

        $this->artisan('accounts:dispatch-due-purges')->assertSuccessful();

        Queue::assertPushed(PurgeDeletedUser::class, 1);
        Queue::assertPushed(PurgeDeletedUser::class, fn (PurgeDeletedUser $job): bool => $job->userId === $due->id && $job->requestedAt === $dueAt->toISOString());
    }
}

<?php

namespace App\Jobs;

use App\Enums\UserStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class PurgeDeletedUser implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $userId, public string $requestedAt) {}

    public function handle(): void
    {
        $requestedAt = CarbonImmutable::parse($this->requestedAt)->utc();

        DB::transaction(function () use ($requestedAt): void {
            $user = User::query()->lockForUpdate()->find($this->userId);

            if ($user === null
                || $user->status !== UserStatus::PendingDeletion
                || $user->deletion_requested_at === null
                || ! $user->deletion_requested_at->equalTo($requestedAt)
                || $user->deletion_requested_at->isAfter(now()->subDays((int) config('data-control.deletion.purge_days', 30)))) {
                return;
            }

            $user->notifications()->delete();
            $user->delete();
        });
    }
}

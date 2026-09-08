<?php

namespace App\Jobs;

use App\Enums\UserStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

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

            $disk = Storage::disk((string) config('data-control.exports.disk', 'exports'));
            foreach ($user->dataExports as $export) {
                if ($export->path !== null && $disk->exists($export->path) && ! $disk->delete($export->path)) {
                    throw new RuntimeException('Personal data export could not be purged.');
                }
            }

            $user->delete();
        });
    }
}

<?php

namespace App\Console\Commands;

use App\Enums\UserStatus;
use App\Jobs\PurgeDeletedUser;
use App\Models\User;
use Illuminate\Console\Command;

class DispatchDueAccountPurges extends Command
{
    protected $signature = 'accounts:dispatch-due-purges';

    protected $description = 'Dispatch purge jobs for accounts whose deletion deadline has passed';

    public function handle(): int
    {
        User::query()
            ->where('status', UserStatus::PendingDeletion)
            ->whereNotNull('deletion_requested_at')
            ->where('deletion_requested_at', '<=', now()->subDays((int) config('data-control.deletion.purge_days', 30)))
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    PurgeDeletedUser::dispatch($user->id, $user->deletion_requested_at->toISOString());
                }
            });

        return self::SUCCESS;
    }
}

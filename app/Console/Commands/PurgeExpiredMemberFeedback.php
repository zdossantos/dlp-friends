<?php

namespace App\Console\Commands;

use App\Enums\UserStatus;
use App\Models\MemberFeedback;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class PurgeExpiredMemberFeedback extends Command
{
    protected $signature = 'feedback:purge-expired';

    protected $description = 'Purge member feedback after two years, except banned owners';

    public function handle(): int
    {
        $cutoff = now()->subYears(2);

        MemberFeedback::query()->where('created_at', '<=', $cutoff)->chunkById(500, function ($feedback) use ($cutoff): void {
            DB::transaction(function () use ($feedback, $cutoff): void {
                User::query()->whereKey($feedback->pluck('user_id')->unique())->orderBy('id')->lockForUpdate()->get();

                MemberFeedback::query()->whereKey($feedback->modelKeys())
                    ->where('created_at', '<=', $cutoff)
                    ->whereHas('user', fn (Builder $query) => $query->where('status', '!=', UserStatus::Banned))
                    ->delete();
            }, 3);
        });

        return self::SUCCESS;
    }
}

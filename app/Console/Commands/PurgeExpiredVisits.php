<?php

namespace App\Console\Commands;

use App\Enums\UserStatus;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class PurgeExpiredVisits extends Command
{
    protected $signature = 'visits:purge-expired';

    protected $description = 'Purge visits ended at least thirty calendar days ago, except banned owners';

    public function handle(): int
    {
        $cutoff = CarbonImmutable::today('Europe/Paris')->subDays(30)->toDateString();

        Visit::query()->where('ends_on', '<=', $cutoff)->chunkById(500, function ($visits) use ($cutoff): void {
            DB::transaction(function () use ($visits, $cutoff): void {
                User::query()->whereKey($visits->pluck('user_id')->unique())->orderBy('id')->lockForUpdate()->get();

                Visit::query()->whereKey($visits->modelKeys())
                    ->where('ends_on', '<=', $cutoff)
                    ->whereHas('user', fn (Builder $query) => $query->where('status', '!=', UserStatus::Banned))
                    ->delete();
            }, 3);
        });

        return self::SUCCESS;
    }
}

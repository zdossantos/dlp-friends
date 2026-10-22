<?php

namespace App\Console\Commands;

use App\Actions\LockTripAccounts;
use App\Enums\UserStatus;
use App\Models\Trip;
use App\Models\User;
use App\Support\TripRetentionAccounts;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class PurgeExpiredTrips extends Command
{
    protected $signature = 'trips:purge-expired';

    protected $description = 'Purge trips thirty days after departure or earlier cancellation, except frozen data';

    public function handle(): int
    {
        $cutoff = now()->subDays(30);
        Trip::query()->where(fn (Builder $query) => $query->where('departs_at', '<=', $cutoff)->orWhere('cancelled_at', '<=', $cutoff))
            ->chunkById(100, function ($trips) use ($cutoff): void {
                foreach ($trips as $candidate) {
                    DB::transaction(function () use ($candidate, $cutoff): void {
                        $graph = app(TripRetentionAccounts::class);
                        app(LockTripAccounts::class)->handle($graph->forTrips([$candidate->id]));
                        $trip = Trip::query()->lockForUpdate()->find($candidate->id);
                        if ($trip === null) {
                            return;
                        }
                        $terminalAt = $trip->cancelled_at !== null && $trip->cancelled_at->isBefore($trip->departs_at) ? $trip->cancelled_at : $trip->departs_at;
                        if ($terminalAt->isAfter($cutoff) || User::query()->whereKey($graph->forTrips([$trip->id]))->where('status', UserStatus::Banned)->orderBy('id')->lockForUpdate()->get()->isNotEmpty()) {
                            return;
                        }
                        // Reports use nullOnDelete; only their minimal snapshot survives.
                        $trip->delete();
                    }, 3);
                }
            });

        return self::SUCCESS;
    }
}

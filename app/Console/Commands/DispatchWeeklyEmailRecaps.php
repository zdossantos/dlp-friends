<?php

namespace App\Console\Commands;

use App\Actions\BuildWeeklyEmailRecap;
use App\Enums\UserStatus;
use App\Jobs\SendWeeklyEmailRecap;
use App\Models\User;
use App\Models\WeeklyEmailRecapDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DispatchWeeklyEmailRecaps extends Command
{
    protected $signature = 'notifications:dispatch-weekly-recaps';

    protected $description = 'Queue eligible weekly email recaps for the most recent Sunday deadline';

    public function handle(BuildWeeklyEmailRecap $build): int
    {
        $now = CarbonImmutable::now('Europe/Paris');
        $periodEnd = $now->startOfWeek(CarbonImmutable::SUNDAY)->setTime(15, 0);
        if ($periodEnd->isAfter($now)) {
            $periodEnd = $periodEnd->subWeek();
        }
        $periodEnd = $periodEnd->utc();

        User::query()->where('status', UserStatus::Active)->where('weekly_recap_messages', true)
            ->whereNotNull('email_verified_at')->whereNull('deletion_requested_at')
            ->chunkById(100, function ($users) use ($build, $periodEnd): void {
                foreach ($users as $user) {
                    if ($build->handle($user, $periodEnd) === null) {
                        continue;
                    }
                    DB::transaction(function () use ($user, $periodEnd): void {
                        WeeklyEmailRecapDelivery::query()->insertOrIgnore([
                            'user_id' => $user->id, 'period_ends_at' => $periodEnd,
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                        $delivery = WeeklyEmailRecapDelivery::query()->where('user_id', $user->id)
                            ->where('period_ends_at', $periodEnd)->firstOrFail();
                        if ($delivery->sent_at === null && $delivery->skipped_at === null) {
                            SendWeeklyEmailRecap::dispatch($delivery->id)->afterCommit();
                        }
                    });
                }
            });

        return self::SUCCESS;
    }
}

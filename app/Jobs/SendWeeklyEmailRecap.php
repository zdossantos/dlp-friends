<?php

namespace App\Jobs;

use App\Actions\BuildWeeklyEmailRecap;
use App\Mail\WeeklyEmailRecapMail;
use App\Models\User;
use App\Models\WeeklyEmailRecapDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendWeeklyEmailRecap implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly int $deliveryId) {}

    public function handle(BuildWeeklyEmailRecap $build): void
    {
        DB::transaction(function () use ($build): void {
            $delivery = WeeklyEmailRecapDelivery::query()->lockForUpdate()->find($this->deliveryId);
            if ($delivery === null || $delivery->sent_at !== null || $delivery->skipped_at !== null) {
                return;
            }
            $user = User::query()->lockForUpdate()->find($delivery->user_id);
            $expiresAt = $delivery->period_ends_at->setTimezone('Europe/Paris')->addWeek();
            $recap = $user !== null && CarbonImmutable::now()->isBefore($expiresAt)
                ? $build->handle($user, $delivery->period_ends_at) : null;
            if ($recap === null) {
                $delivery->update(['skipped_at' => now()]);

                return;
            }

            Mail::to($user->email)->send((new WeeklyEmailRecapMail($recap['conversations'], $recap['matches']))->locale($user->preferredLocale()));
            $delivery->update(['sent_at' => now()]);
        });
    }
}

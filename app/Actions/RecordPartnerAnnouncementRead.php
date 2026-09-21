<?php

namespace App\Actions;

use App\Models\PartnerAnnouncementDelivery;
use App\Models\PartnerAnnouncementMetric;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;

final class RecordPartnerAnnouncementRead
{
    public function handle(User $user, DatabaseNotification $notification): void
    {
        DB::transaction(function () use ($user, $notification): void {
            // Engagement lock order: notification, delivery, aggregate metrics.
            $lockedNotification = $user->notifications()
                ->whereKey($notification->id)
                ->lockForUpdate()
                ->firstOrFail();
            $delivery = PartnerAnnouncementDelivery::query()
                ->where('notification_id', $lockedNotification->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($delivery === null) {
                if ($lockedNotification->read_at === null) {
                    $lockedNotification->markAsRead();
                }

                return;
            }

            $shouldCount = $lockedNotification->read_at === null && $delivery->read_at === null;

            if (! $shouldCount) {
                if ($lockedNotification->read_at === null) {
                    $lockedNotification->markAsRead();
                }

                return;
            }

            $metric = $delivery->partner_announcement_id === null
                ? null
                : PartnerAnnouncementMetric::query()
                    ->where('partner_announcement_id', $delivery->partner_announcement_id)
                    ->lockForUpdate()
                    ->first();

            $lockedNotification->markAsRead();
            $delivery->update(['read_at' => now()]);
            $metric?->increment('read_count');
        });
    }
}

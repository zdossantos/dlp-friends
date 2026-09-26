<?php

namespace App\Actions;

use App\Models\PartnerAnnouncementDelivery;
use App\Models\PartnerAnnouncementMetric;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;

final class DismissPartnerAnnouncement
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
                ->firstOrFail();

            if ($delivery->dismissed_at !== null) {
                return;
            }

            $metric = $delivery->partner_announcement_id === null
                ? null
                : PartnerAnnouncementMetric::query()
                    ->where('partner_announcement_id', $delivery->partner_announcement_id)
                    ->lockForUpdate()
                    ->first();

            $delivery->update(['dismissed_at' => now()]);
            $metric?->increment('dismissed_count');
        });
    }
}

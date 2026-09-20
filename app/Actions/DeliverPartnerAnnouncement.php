<?php

namespace App\Actions;

use App\Enums\PartnerDeliveryStatus;
use App\Models\PartnerAnnouncementDelivery;
use App\Models\PartnerAnnouncementMetric;
use App\Models\User;
use App\Notifications\PartnerAnnouncementNotification;
use Illuminate\Notifications\Channels\BroadcastChannel;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class DeliverPartnerAnnouncement
{
    public function handle(PartnerAnnouncementDelivery $delivery): void
    {
        try {
            DB::transaction(function () use ($delivery): void {
                // Shared eligibility lock order: user, related eligibility reads, delivery.
                $recipient = User::query()
                    ->whereKey($delivery->user_id)
                    ->lockForUpdate()
                    ->first();
                $locked = PartnerAnnouncementDelivery::query()
                    ->whereKey($delivery->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (in_array($locked->status, [
                    PartnerDeliveryStatus::Delivered,
                    PartnerDeliveryStatus::Skipped,
                ], true)) {
                    return;
                }

                if ($locked->status !== PartnerDeliveryStatus::Pending) {
                    return;
                }

                if ($recipient === null || ! User::query()
                    ->eligibleForPartnerAnnouncements()
                    ->whereKey($recipient->id)
                    ->exists()) {
                    $locked->update([
                        'status' => PartnerDeliveryStatus::Skipped,
                        'attempts' => $locked->attempts + 1,
                        'last_error' => null,
                    ]);

                    return;
                }

                $notificationId = (string) Str::uuid();
                $notification = new PartnerAnnouncementNotification($locked->announcement);
                $notification->id = $notificationId;
                app(DatabaseChannel::class)->send($recipient, $notification);

                $locked->update([
                    'notification_id' => $notificationId,
                    'status' => PartnerDeliveryStatus::Delivered,
                    'attempts' => $locked->attempts + 1,
                    'last_error' => null,
                    'delivered_at' => now(),
                ]);
                PartnerAnnouncementMetric::query()
                    ->where('partner_announcement_id', $locked->partner_announcement_id)
                    ->increment('delivered_count');

                DB::afterCommit(static function () use ($recipient, $notification): void {
                    app(BroadcastChannel::class)->send($recipient, $notification);
                });
            });
        } catch (Throwable $exception) {
            DB::transaction(function () use ($delivery): void {
                $locked = PartnerAnnouncementDelivery::query()
                    ->whereKey($delivery->id)
                    ->lockForUpdate()
                    ->first();

                if ($locked?->status === PartnerDeliveryStatus::Pending) {
                    $locked->increment('attempts');
                }
            });

            throw $exception;
        }
    }

    public function markFailed(int $deliveryId, Throwable $exception): void
    {
        DB::transaction(function () use ($deliveryId, $exception): void {
            $delivery = PartnerAnnouncementDelivery::query()
                ->whereKey($deliveryId)
                ->lockForUpdate()
                ->first();

            if ($delivery === null || $delivery->status !== PartnerDeliveryStatus::Pending) {
                return;
            }

            $delivery->update([
                'status' => PartnerDeliveryStatus::Failed,
                'last_error' => Str::limit(class_basename($exception).': notification delivery failed', 1000, ''),
            ]);
        });
    }
}

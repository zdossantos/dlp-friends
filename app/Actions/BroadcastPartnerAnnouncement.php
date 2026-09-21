<?php

namespace App\Actions;

use App\Enums\PartnerDeliveryStatus;
use App\Models\PartnerAnnouncementDelivery;
use App\Notifications\PartnerAnnouncementNotification;
use Illuminate\Notifications\Channels\BroadcastChannel;
use Illuminate\Support\Facades\DB;

final class BroadcastPartnerAnnouncement
{
    public function handle(int $deliveryId): void
    {
        DB::transaction(function () use ($deliveryId): void {
            $delivery = PartnerAnnouncementDelivery::query()
                ->whereKey($deliveryId)
                ->lockForUpdate()
                ->first();

            if ($delivery === null
                || $delivery->status !== PartnerDeliveryStatus::Delivered
                || $delivery->broadcasted_at !== null) {
                return;
            }

            $recipient = $delivery->user()->firstOrFail();
            $databaseNotification = $delivery->notification()->firstOrFail();
            $notification = new PartnerAnnouncementNotification(
                $delivery->announcement()->first(),
                $databaseNotification->data,
            );
            $notification->id = (string) $databaseNotification->id;

            app(BroadcastChannel::class)->send($recipient, $notification);

            $delivery->update(['broadcasted_at' => now()]);
        });
    }
}

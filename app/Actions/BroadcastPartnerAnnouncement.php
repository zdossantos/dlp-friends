<?php

namespace App\Actions;

use App\Enums\PartnerDeliveryStatus;
use App\Models\PartnerAnnouncementDelivery;
use App\Notifications\PartnerAnnouncementNotification;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastingFactory;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use Illuminate\Support\Facades\DB;

final class BroadcastPartnerAnnouncement
{
    public function __construct(private readonly BroadcastingFactory $broadcasting) {}

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

            $event = new BroadcastNotificationCreated(
                $recipient,
                $notification,
                $notification->toBroadcast($recipient)->data,
            );
            (new BroadcastEvent($event))->handle($this->broadcasting);

            $delivery->update(['broadcasted_at' => now()]);
        });
    }
}

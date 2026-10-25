<?php

namespace App\Jobs;

use App\Enums\PartnerDeliveryStatus;
use App\Models\PartnerAnnouncementDelivery;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\PartnerAnnouncementNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class PushPartnerAnnouncement implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $deliveryId) {}

    public function handle(WebPushChannel $channel): void
    {
        $delivery = PartnerAnnouncementDelivery::query()->with('user', 'notification', 'announcement')->find($this->deliveryId);
        if ($delivery?->status !== PartnerDeliveryStatus::Delivered || $delivery->notification === null) {
            return;
        }

        $notification = new PartnerAnnouncementNotification($delivery->announcement, $delivery->notification->data);
        $notification->id = (string) $delivery->notification_id;
        $channel->send($delivery->user, $notification);
    }
}

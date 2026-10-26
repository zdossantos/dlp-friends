<?php

namespace App\Jobs;

use App\Models\ApplicationAnnouncementDelivery;
use App\Models\User;
use App\Notifications\ApplicationAnnouncementNotification;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastingFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class DeliverApplicationAnnouncement implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $deliveryId) {}

    public function handle(DatabaseChannel $database, WebPushChannel $push, BroadcastingFactory $broadcasting): void
    {
        $delivery = ApplicationAnnouncementDelivery::query()->find($this->deliveryId);
        if ($delivery === null) {
            return;
        }
        DB::transaction(function () use ($database, $delivery): void {
            // Use the same user-first lock order as account and preference changes.
            $recipient = User::query()->lockForUpdate()->find($delivery->user_id);
            $delivery = ApplicationAnnouncementDelivery::query()->lockForUpdate()->find($this->deliveryId);
            if ($delivery === null || ! in_array($delivery->status, ['pending', 'failed'], true)
                || $delivery->announcement->sent_at === null) {
                return;
            }
            if ($recipient === null || ! User::query()->eligibleForApplicationAnnouncements()->whereKey($recipient->id)->exists()) {
                $delivery->update(['status' => 'skipped']);

                return;
            }
            $notification = new ApplicationAnnouncementNotification($delivery->announcement);
            $notification->id = (string) Str::uuid();
            $database->send($recipient, $notification);
            $delivery->update([
                'status' => 'delivered', 'notification_id' => $notification->id,
                'attempts' => $delivery->attempts + 1, 'delivered_at' => now(),
            ]);
        }, 3);

        $delivery = ApplicationAnnouncementDelivery::query()->with('announcement', 'user', 'notification')->find($this->deliveryId);
        if ($delivery?->status !== 'delivered' || $delivery->notification === null) {
            return;
        }
        $notification = new ApplicationAnnouncementNotification($delivery->announcement, $delivery->notification->data);
        $notification->id = $delivery->notification_id;
        try {
            if ($delivery->announcement->send_push) {
                $push->send($delivery->user, $notification);
            }
        } finally {
            DB::transaction(function () use ($broadcasting): void {
                $delivery = ApplicationAnnouncementDelivery::query()->lockForUpdate()->find($this->deliveryId);
                if ($delivery === null || $delivery->broadcasted_at !== null || $delivery->notification === null
                    || ! User::query()->eligibleForApplicationAnnouncements()->whereKey($delivery->user_id)->exists()) {
                    return;
                }
                $notification = new ApplicationAnnouncementNotification($delivery->announcement, $delivery->notification->data);
                $notification->id = (string) $delivery->notification_id;
                $event = new BroadcastNotificationCreated($delivery->user, $notification, $notification->toBroadcast($delivery->user)->data);
                (new BroadcastEvent($event))->handle($broadcasting);
                $delivery->update(['broadcasted_at' => now()]);
            });
        }
    }

    public function failed(?Throwable $exception): void
    {
        ApplicationAnnouncementDelivery::query()->whereKey($this->deliveryId)
            ->where('status', 'pending')->update(['status' => 'failed']);
    }
}

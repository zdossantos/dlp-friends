<?php

namespace App\Jobs;

use App\Actions\DeliverWebPushNotification;
use App\Contracts\WebPushNotification;
use App\Models\WebPushSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SendWebPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $subscriptionId, public WebPushNotification $notification) {}

    public function handle(DeliverWebPushNotification $deliver): void
    {
        $subscription = WebPushSubscription::query()->find($this->subscriptionId);

        if ($subscription !== null) {
            $deliver->handle($subscription, $this->notification);
        }
    }
}

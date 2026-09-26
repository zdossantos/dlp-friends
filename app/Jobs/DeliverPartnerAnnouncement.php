<?php

namespace App\Jobs;

use App\Actions\DeliverPartnerAnnouncement as DeliverAnnouncement;
use App\Actions\FinalizePartnerAnnouncement;
use App\Models\PartnerAnnouncementDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class DeliverPartnerAnnouncement implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $deliveryId) {}

    public function handle(
        DeliverAnnouncement $deliver,
        FinalizePartnerAnnouncement $finalize,
    ): void {
        $delivery = PartnerAnnouncementDelivery::query()->find($this->deliveryId);

        if ($delivery === null) {
            return;
        }

        $deliver->handle($delivery);
        $announcement = $delivery->announcement()->first();

        if ($announcement !== null) {
            $finalize->handle($announcement);
        }
    }

    public function failed(Throwable $exception): void
    {
        app(DeliverAnnouncement::class)->markFailed($this->deliveryId, $exception);
    }
}

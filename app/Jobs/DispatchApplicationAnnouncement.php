<?php

namespace App\Jobs;

use App\Models\ApplicationAnnouncement;
use App\Models\ApplicationAnnouncementDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;

final class DispatchApplicationAnnouncement implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $announcementId) {}

    public function handle(): void
    {
        $announcement = ApplicationAnnouncement::query()->find($this->announcementId);
        if ($announcement?->sent_at === null) {
            return;
        }
        $announcement->deliveries()->where('status', '!=', 'skipped')
            ->select('id')->chunkById(500, function (Collection $deliveries): void {
                $deliveries->each(fn (ApplicationAnnouncementDelivery $delivery) => DeliverApplicationAnnouncement::dispatch($delivery->id));
            });
    }
}

<?php

namespace App\Jobs;

use App\Actions\BroadcastPartnerAnnouncement as BroadcastAnnouncement;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class BroadcastPartnerAnnouncement implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $deliveryId) {}

    public function handle(BroadcastAnnouncement $broadcast): void
    {
        $broadcast->handle($this->deliveryId);
    }
}

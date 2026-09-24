<?php

namespace App\Jobs;

use App\Actions\FinalizePartnerAnnouncement;
use App\Actions\PreparePartnerAnnouncementAudience as PrepareAudience;
use App\Models\PartnerAnnouncement;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class PreparePartnerAnnouncementAudience implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $announcementId) {}

    public function handle(PrepareAudience $prepare, FinalizePartnerAnnouncement $finalize): void
    {
        $announcement = PartnerAnnouncement::query()->find($this->announcementId);

        if ($announcement === null) {
            return;
        }

        $prepare->handle($announcement);
        $finalize->handle($announcement->fresh());
    }
}

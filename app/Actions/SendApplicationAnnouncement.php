<?php

namespace App\Actions;

use App\Jobs\DispatchApplicationAnnouncement;
use App\Models\ApplicationAnnouncement;
use Illuminate\Support\Facades\DB;

final class SendApplicationAnnouncement
{
    public function handle(ApplicationAnnouncement $announcement): void
    {
        DB::transaction(function () use ($announcement): void {
            $locked = ApplicationAnnouncement::query()->lockForUpdate()->findOrFail($announcement->id);
            if ($locked->sent_at === null) {
                $locked->update(['sent_at' => now()]);
            }
            // Re-dispatch also recovers a queue interruption after the confirmation commit.
            DB::afterCommit(fn () => DispatchApplicationAnnouncement::dispatch($locked->id));
        });
    }
}

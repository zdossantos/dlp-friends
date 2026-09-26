<?php

namespace App\Actions;

use App\Enums\PartnerAnnouncementStatus;
use App\Enums\PartnerDeliveryStatus;
use App\Jobs\DeliverPartnerAnnouncement;
use App\Models\PartnerAnnouncement;
use App\Models\PartnerAnnouncementDelivery;
use App\Models\PartnerAnnouncementMetric;
use App\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PreparePartnerAnnouncementAudience
{
    public function handle(PartnerAnnouncement $announcement): void
    {
        DB::transaction(function () use ($announcement): void {
            $profile = PartnerProfile::query()
                ->whereKey($announcement->partner_profile_id)
                ->lockForUpdate()
                ->firstOrFail();
            $locked = PartnerAnnouncement::query()
                ->whereKey($announcement->id)
                ->where('partner_profile_id', $profile->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== PartnerAnnouncementStatus::Sending) {
                return;
            }

            if ($locked->audience_prepared_at === null) {
                User::query()
                    ->eligibleForPartnerAnnouncements()
                    ->select('users.id')
                    ->chunkById(500, function (Collection $users) use ($locked): void {
                        $now = now();
                        $rows = $users->map(fn (User $user): array => [
                            'partner_announcement_id' => $locked->id,
                            'source_announcement_id' => $locked->id,
                            'announcement_title' => $locked->title,
                            'announcement_content' => $locked->content,
                            'announcement_destination_url' => $locked->destination_url,
                            'user_id' => $user->id,
                            'click_token' => hash('sha256', Str::uuid()->toString()),
                            'status' => PartnerDeliveryStatus::Pending->value,
                            'attempts' => 0,
                            'click_count' => 0,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ])->all();

                        PartnerAnnouncementDelivery::query()->insertOrIgnore($rows);
                    });

                $preparedCount = $locked->deliveries()->count();
                PartnerAnnouncementMetric::query()->updateOrCreate(
                    ['partner_announcement_id' => $locked->id],
                    ['prepared_count' => $preparedCount],
                );
                $locked->update(['audience_prepared_at' => now()]);
            }

            $locked->deliveries()
                ->where('status', PartnerDeliveryStatus::Pending)
                ->select('id')
                ->chunkById(500, function (Collection $deliveries): void {
                    $deliveries->each(static function (PartnerAnnouncementDelivery $delivery): void {
                        $deliveryId = $delivery->id;
                        DB::afterCommit(static fn () => DeliverPartnerAnnouncement::dispatch($deliveryId));
                    });
                });
        });
    }
}

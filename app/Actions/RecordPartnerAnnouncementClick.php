<?php

namespace App\Actions;

use App\Models\PartnerAnnouncementDelivery;
use App\Models\PartnerAnnouncementMetric;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final class RecordPartnerAnnouncementClick
{
    public function handle(string $token): string
    {
        return DB::transaction(function () use ($token): string {
            // The equality lookup uses the unique index; hash_equals enforces byte-exact matching.
            $delivery = PartnerAnnouncementDelivery::query()
                ->where('click_token', $token)
                ->lockForUpdate()
                ->firstOrFail();

            if (! hash_equals($delivery->click_token, $token)) {
                throw (new ModelNotFoundException)->setModel(PartnerAnnouncementDelivery::class);
            }

            // Engagement lock order: delivery, aggregate metrics.
            $metric = $delivery->partner_announcement_id === null
                ? null
                : PartnerAnnouncementMetric::query()
                    ->where('partner_announcement_id', $delivery->partner_announcement_id)
                    ->lockForUpdate()
                    ->first();
            $firstClick = $delivery->first_clicked_at === null;

            $delivery->update([
                'first_clicked_at' => $delivery->first_clicked_at ?? now(),
                'click_count' => $delivery->click_count + 1,
            ]);
            $metric?->increment('total_click_count');

            if ($firstClick) {
                $metric?->increment('unique_click_count');
            }

            return $delivery->announcement_destination_url;
        });
    }
}

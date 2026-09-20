<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PartnerAnnouncementStatus;
use App\Enums\PartnerDeliveryStatus;
use App\Http\Controllers\Controller;
use App\Jobs\BroadcastPartnerAnnouncement;
use App\Jobs\PreparePartnerAnnouncementAudience;
use App\Models\PartnerAnnouncement;
use App\Models\PartnerAnnouncementDelivery;
use App\Models\PartnerProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PartnerAnnouncementRetryController extends Controller
{
    public function __invoke(PartnerAnnouncement $announcement): RedirectResponse
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

            if (! in_array($locked->status, [
                PartnerAnnouncementStatus::Sending,
                PartnerAnnouncementStatus::Sent,
            ], true)) {
                throw ValidationException::withMessages([
                    'announcement' => __('notifications.admin.not_sending'),
                ]);
            }

            if ($locked->status === PartnerAnnouncementStatus::Sending) {
                PartnerAnnouncementDelivery::query()
                    ->where('partner_announcement_id', $locked->id)
                    ->where('status', PartnerDeliveryStatus::Failed)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->each(static function (PartnerAnnouncementDelivery $delivery): void {
                        $delivery->update([
                            'status' => PartnerDeliveryStatus::Pending,
                            'last_error' => null,
                        ]);
                    });
            }

            PartnerAnnouncementDelivery::query()
                ->where('partner_announcement_id', $locked->id)
                ->where('status', PartnerDeliveryStatus::Delivered)
                ->whereNotNull('notification_id')
                ->whereNull('broadcasted_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id')
                ->each(static function (int $deliveryId): void {
                    DB::afterCommit(static fn () => BroadcastPartnerAnnouncement::dispatch($deliveryId));
                });

            if ($locked->status === PartnerAnnouncementStatus::Sending) {
                $announcementId = $locked->id;
                DB::afterCommit(static fn () => PreparePartnerAnnouncementAudience::dispatch($announcementId));
            }
        });

        return to_route('admin.partner-announcements.index')
            ->with('success', __('notifications.admin.retry_started'));
    }
}

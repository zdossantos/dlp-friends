<?php

namespace App\Data;

use App\Enums\UserStatus;
use App\Models\TripDepartureReport;
use Illuminate\Support\Facades\Gate;

final class TripDepartureReportData
{
    /** @return array<string, mixed> */
    public static function detail(TripDepartureReport $report): array
    {
        $report->loadMissing(['reporter.profile', 'target.profile', 'target.roles']);

        return [
            'id' => $report->id, 'details' => $report->details, 'decision' => $report->decision,
            'scheduled_departure_at' => $report->scheduled_departure_at->toISOString(), 'left_at' => $report->left_at->toISOString(),
            'closed_at' => $report->closed_at?->toISOString(), 'created_at' => $report->created_at?->toISOString(),
            'reporter' => ['id' => $report->reporter_user_id, 'name' => $report->reporter?->profile->display_name ?? __('moderation.deleted_member')],
            'target' => ['id' => $report->target_user_id, 'name' => $report->target?->profile->display_name ?? __('moderation.deleted_member'), 'banned' => $report->target?->status === UserStatus::Banned, 'can_ban' => $report->target !== null && Gate::allows('ban', $report->target)],
        ];
    }
}

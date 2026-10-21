<?php

namespace App\Actions;

use App\Models\ModerationAudit;
use App\Models\TripDepartureReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class CloseTripDepartureReport
{
    public function handle(User $admin, TripDepartureReport $report, string $decision): void
    {
        DB::transaction(function () use ($admin, $report, $decision): void {
            $admin = User::query()->lockForUpdate()->findOrFail($admin->id);
            $report = TripDepartureReport::query()->lockForUpdate()->findOrFail($report->id);
            Gate::forUser($admin)->authorize('view', $report);
            if ($report->closed_at !== null) {
                return;
            }
            Validator::make(['decision' => $decision], ['decision' => ['required', 'string', 'max:1000', 'not_regex:/^\s*$/u']])->validate();
            $report->update(['decision' => $decision, 'decided_by_user_id' => $admin->id, 'closed_at' => now(), 'open_key' => null]);
            ModerationAudit::query()->create(['actor_user_id' => $admin->id, 'target_user_id' => $report->target_user_id, 'trip_departure_report_id' => $report->id, 'operation' => 'close']);
        }, 3);
    }
}

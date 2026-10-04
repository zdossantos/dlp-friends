<?php

namespace App\Actions;

use App\Models\ConversationReport;
use App\Models\ModerationAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class CloseConversationReport
{
    public function handle(User $admin, ConversationReport $report, string $decision): void
    {
        DB::transaction(function () use ($admin, $report, $decision): void {
            $admin = User::query()->lockForUpdate()->findOrFail($admin->id);
            $report = ConversationReport::query()->lockForUpdate()->findOrFail($report->id);
            Gate::forUser($admin)->authorize('close', $report);
            $report->update(['decision' => $decision, 'decided_by_user_id' => $admin->id, 'closed_at' => now(), 'open_key' => null]);
            ModerationAudit::query()->create(['actor_user_id' => $admin->id, 'target_user_id' => $report->target_user_id, 'conversation_report_id' => $report->id, 'operation' => 'close']);
        });
    }
}

<?php

namespace App\Actions;

use App\Enums\ConversationReportReason;
use App\Enums\UserStatus;
use App\Models\Conversation;
use App\Models\ConversationReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ReportConversation
{
    public function __construct(private BlockUser $blockUser) {}

    public function handle(User $reporter, Conversation $conversation, ConversationReportReason $reason, ?string $details, bool $block): ConversationReport
    {
        return DB::transaction(function () use ($reporter, $conversation, $reason, $details, $block): ConversationReport {
            $match = $conversation->memberMatch;
            $users = User::query()->whereKey([$match->user_low_id, $match->user_high_id])->orderBy('id')->lockForUpdate()->get();
            $reporter = $users->firstWhere('id', $reporter->id);
            abort_unless($reporter instanceof User && $reporter->status === UserStatus::Active && $reporter->hasVerifiedEmail() && ($reporter->age ?? 0) >= 18, 403);
            $conversation = Conversation::query()->lockForUpdate()->findOrFail($conversation->id);
            Gate::forUser($reporter)->authorize('view', $conversation);
            $target = $users->first(fn (User $user): bool => ! $user->is($reporter));
            abort_unless($target instanceof User, 403);
            if ($block && $target->load('roles')->hasRole('admin')) {
                throw ValidationException::withMessages(['block' => __('moderation.block_admin')]);
            }
            $key = $conversation->id.':'.$reporter->id;
            if (ConversationReport::query()->where('open_key', $key)->exists()) {
                throw ValidationException::withMessages(['conversation' => __('moderation.already_open')]);
            }
            $report = ConversationReport::query()->create(['conversation_id' => $conversation->id, 'reporter_user_id' => $reporter->id, 'target_user_id' => $target->id, 'reason' => $reason, 'details' => $details, 'open_key' => $key]);
            if ($block) {
                $this->blockUser->handle($reporter, $target);
            }

            return $report;
        }, 3);
    }
}

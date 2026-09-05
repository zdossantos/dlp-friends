<?php

namespace App\Actions;

use App\Data\AdminMemberConversationResult;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Conversation;
use App\Models\MemberMatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OpenAdminMemberConversation
{
    public function handle(User $admin, User $member): AdminMemberConversationResult
    {
        return DB::transaction(function () use ($admin, $member): AdminMemberConversationResult {
            [$lowId, $highId] = collect([$admin->id, $member->id])->sort()->values()->all();
            $lockedUsers = User::query()
                ->with(['roles', 'profile.avatar'])
                ->whereKey([$lowId, $highId])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $lockedAdmin = $lockedUsers->get($admin->id);
            $lockedMember = $lockedUsers->get($member->id);

            if (! $lockedAdmin instanceof User
                || ! $lockedMember instanceof User
                || ! $lockedAdmin->hasRole(RoleName::Admin)
                || $lockedMember->hasRole(RoleName::Admin)
                || $lockedMember->status !== UserStatus::Active
                || $lockedMember->profile?->isComplete() !== true
                || $lockedAdmin->hasBlockedRelationshipWith($lockedMember)) {
                throw ValidationException::withMessages([
                    'member' => __('administration.members.conversation_unavailable'),
                ]);
            }

            $match = MemberMatch::query()->firstOrCreate([
                'user_low_id' => $lowId,
                'user_high_id' => $highId,
            ]);
            $conversation = Conversation::query()->firstOrCreate(['match_id' => $match->id]);

            return new AdminMemberConversationResult(
                conversation: $conversation,
                created: $match->wasRecentlyCreated || $conversation->wasRecentlyCreated,
            );
        });
    }
}

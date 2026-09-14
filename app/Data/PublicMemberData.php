<?php

namespace App\Data;

use App\Enums\SwipeDecision;
use App\Models\Block;
use App\Models\Interest;
use App\Models\MemberMatch;
use App\Models\Swipe;
use App\Models\User;

final readonly class PublicMemberData
{
    /** @return array<string, mixed> */
    public static function from(User $viewer, User $member): array
    {
        $member->loadMissing(['profile.avatar', 'profile.interests', 'roles']);
        $profile = $member->profile;
        $avatar = $profile?->avatar;

        abort_if($profile === null || $avatar === null, 404);

        $isSelf = $viewer->is($member);
        $isAdmin = $member->hasRole('admin');
        $canUnblock = ! $isSelf && ! $isAdmin && Block::query()
            ->where('blocker_user_id', $viewer->id)
            ->where('blocked_user_id', $member->id)
            ->exists();
        $isBlockedPair = ! $isSelf && Block::query()
            ->whereIn('blocker_user_id', [$viewer->id, $member->id])
            ->whereIn('blocked_user_id', [$viewer->id, $member->id])
            ->exists();
        $outgoingDecision = ! $isSelf ? Swipe::query()
            ->where('actor_user_id', $viewer->id)
            ->where('target_user_id', $member->id)
            ->value('decision') : null;
        [$lowId, $highId] = collect([$viewer->id, $member->id])->sort()->values()->all();
        $conversation = ! $isSelf && ! $isBlockedPair
            ? MemberMatch::query()
                ->where('user_low_id', $lowId)
                ->where('user_high_id', $highId)
                ->with('conversation')
                ->first()?->conversation
            : null;

        return [
            'canBlock' => ! $isSelf && ! $isAdmin && ! $canUnblock,
            'canLike' => ! $isSelf
                && ! $isBlockedPair
                && $conversation === null
                && ($outgoingDecision === null
                    || $outgoingDecision === SwipeDecision::Pass
                    || $outgoingDecision === SwipeDecision::Pass->value),
            'canUnblock' => $canUnblock,
            'conversationHref' => $conversation !== null
                ? route('conversations.show', $conversation, absolute: false)
                : null,
            'member' => [
                'id' => $member->id,
                'is_admin' => $isAdmin,
                'display_name' => $profile->display_name,
                'age' => $member->age,
                'avatar' => [
                    'id' => $avatar->id,
                    'name' => $avatar->name,
                    'image_url' => route('avatars.image', $avatar),
                    'primary_color' => $avatar->primary_color,
                    'secondary_color' => $avatar->secondary_color,
                ],
                'bio' => $profile->bio,
                'visit_frequency' => $profile->visit_frequency?->value,
                'interests' => $profile->interests
                    ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
                    ->map(fn (Interest $interest): array => [
                        'id' => $interest->id,
                        'name' => $interest->display_name,
                    ])->values()->all(),
            ],
        ];
    }
}

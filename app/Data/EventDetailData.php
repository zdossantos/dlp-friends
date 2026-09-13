<?php

namespace App\Data;

use App\Enums\EventRegistrationStatus;
use App\Models\Block;
use App\Models\Event;
use App\Models\User;
use App\Policies\EventPolicy;
use Illuminate\Support\Collection;

final readonly class EventDetailData
{
    /** @return array<string, mixed> */
    public static function from(Event $event, User $viewer): array
    {
        $data = EventSummaryData::from($event, $viewer);

        if (! (new EventPolicy)->viewPrivateDetails($viewer, $event)) {
            return $data;
        }

        $event->loadMissing('organizer.profile.avatar');

        $accepted = $event->registrations()
            ->where('status', EventRegistrationStatus::Accepted)
            ->with('user.profile.avatar')
            ->get()
            ->pluck('user');
        $participantUsers = collect([$event->organizer])
            ->concat($accepted)
            ->unique('id')
            ->values();
        $participantIds = $participantUsers->pluck('id')->reject(fn (int $id): bool => $id === $viewer->id);
        $blocks = Block::query()
            ->where(function ($query) use ($viewer, $participantIds): void {
                $query->where('blocker_user_id', $viewer->id)
                    ->whereIn('blocked_user_id', $participantIds);
            })
            ->orWhere(function ($query) use ($viewer, $participantIds): void {
                $query->whereIn('blocker_user_id', $participantIds)
                    ->where('blocked_user_id', $viewer->id);
            })
            ->get();
        $privateData = [
            'detailedLocation' => $event->detailed_location,
            'participants' => $participantUsers
                ->map(fn (User $user): array => self::participant(
                    $user,
                    $viewer,
                    $blocks,
                ))
                ->all(),
        ];

        if ($event->organizer_user_id === $viewer->id) {
            $privateData['registrations'] = $event->registrations()
                ->where('status', '!=', EventRegistrationStatus::Blocked)
                ->with('user.profile.avatar')
                ->orderBy('created_at')
                ->get()
                ->map(fn ($registration): array => [
                    'registrationId' => $registration->id,
                    'id' => $registration->user->id,
                    'displayName' => $registration->user->profile?->display_name,
                    'avatar' => self::avatar($registration->user),
                    'status' => $registration->status->value,
                ])->all();
        }

        return $data + $privateData;
    }

    /** @return array<string, mixed> */
    /**
     * @param  Collection<int, Block>  $blocks
     * @return array<string, mixed>
     */
    private static function participant(
        User $user,
        User $viewer,
        $blocks,
    ): array {
        $isSelf = $viewer->is($user);
        $outgoingBlock = ! $isSelf && $blocks->contains(fn (Block $block): bool => $block->blocker_user_id === $viewer->id && $block->blocked_user_id === $user->id);
        $isBlocked = ! $isSelf && $blocks->contains(fn (Block $block): bool => ($block->blocker_user_id === $viewer->id && $block->blocked_user_id === $user->id)
            || ($block->blocker_user_id === $user->id && $block->blocked_user_id === $viewer->id));

        return [
            'id' => $user->id,
            'displayName' => $isBlocked ? null : $user->profile?->display_name,
            'avatar' => $isBlocked ? null : self::avatar($user),
            'isSelf' => $isSelf,
            'isBlocked' => $isBlocked,
            'canUnblock' => $outgoingBlock,
        ];
    }

    /** @return array<string, mixed>|null */
    private static function avatar(User $user): ?array
    {
        $avatar = $user->profile?->avatar;

        if ($avatar === null) {
            return null;
        }

        return [
            'id' => $avatar->id,
            'name' => $avatar->name,
            'image_url' => route('avatars.image', $avatar),
            'primary_color' => $avatar->primary_color,
            'secondary_color' => $avatar->secondary_color,
        ];
    }
}

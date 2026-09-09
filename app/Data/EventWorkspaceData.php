<?php

namespace App\Data;

use App\Enums\EventRegistrationStatus;
use App\Models\Block;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final readonly class EventWorkspaceData
{
    /** @return array<int, array<string, mixed>> */
    public function discovery(User $viewer): array
    {
        $blockedUserIds = Block::query()
            ->where('blocker_user_id', $viewer->id)
            ->orWhere('blocked_user_id', $viewer->id)
            ->get()
            ->map(fn (Block $block): int => $block->blocker_user_id === $viewer->id
                ? $block->blocked_user_id
                : $block->blocker_user_id);

        return Event::query()
            ->whereNull('cancelled_at')
            ->where('starts_at', '>', now())
            ->whereNotIn('organizer_user_id', $blockedUserIds)
            ->withAvailableCapacity()
            ->withAcceptedRegistrationCount()
            ->with('organizer.profile')
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Event $event): array => EventSummaryData::from($event, $viewer))
            ->all();
    }

    /** @return array{organized: array<int, array<string, mixed>>, participating: array<int, array<string, mixed>>} */
    public function mine(User $viewer): array
    {
        $organized = Event::query()
            ->where('organizer_user_id', $viewer->id)
            ->withAcceptedRegistrationCount()
            ->with('organizer.profile')
            ->orderByDesc('starts_at')
            ->get();

        $participating = Event::query()
            ->whereHas('registrations', fn (Builder $registrations) => $registrations
                ->where('user_id', $viewer->id)
                ->whereIn('status', [
                    EventRegistrationStatus::Pending,
                    EventRegistrationStatus::Accepted,
                ]))
            ->withAcceptedRegistrationCount()
            ->with('organizer.profile')
            ->orderByDesc('starts_at')
            ->get();

        return [
            'organized' => $organized
                ->map(fn (Event $event): array => EventSummaryData::from($event, $viewer))
                ->all(),
            'participating' => $participating
                ->map(fn (Event $event): array => EventSummaryData::from($event, $viewer))
                ->all(),
        ];
    }
}

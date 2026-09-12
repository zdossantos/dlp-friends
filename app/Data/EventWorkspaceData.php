<?php

namespace App\Data;

use App\Enums\EventRegistrationStatus;
use App\Models\Block;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class EventWorkspaceData
{
    public function context(Request $request): string
    {
        return $request->query('origin') === 'mine' ? 'mine' : 'discover';
    }

    /** @param array<string, mixed>|null $panel */
    public function render(User $viewer, string $context, ?array $panel): Response
    {
        if ($context === 'mine') {
            return Inertia::render('Events/Mine', [
                ...$this->mine($viewer),
                'context' => 'mine',
                'panel' => $panel,
                'closeHref' => route('events.mine', absolute: false),
            ]);
        }

        return Inertia::render('Events/Index', [
            'events' => $this->discovery($viewer),
            'context' => 'discover',
            'panel' => $panel,
            'closeHref' => route('events.index', absolute: false),
        ]);
    }

    public function detailUrl(Event $event, string $context): string
    {
        $parameters = ['event' => $event];

        if ($context === 'mine') {
            $parameters['origin'] = 'mine';
        }

        return route('events.show', $parameters, absolute: false);
    }

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

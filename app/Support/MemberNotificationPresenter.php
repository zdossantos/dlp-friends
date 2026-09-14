<?php

namespace App\Support;

use App\Models\Conversation;
use App\Models\Event;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Route;

final class MemberNotificationPresenter
{
    /** @return array{id: string, category: string, translation_key: string, parameters: array<string, string|int|null>, target_url: string, read_at: string|null, created_at: string|null} */
    public function present(DatabaseNotification $notification, User $viewer): array
    {
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'category' => (string) ($data['category'] ?? ''),
            'translation_key' => (string) ($data['translation_key'] ?? ''),
            'parameters' => is_array($data['parameters'] ?? null) ? $data['parameters'] : [],
            'target_url' => $this->targetUrl($data, $viewer),
            'read_at' => $notification->read_at?->toISOString(),
            'created_at' => $notification->created_at?->toISOString(),
        ];
    }

    /** @param array<string, mixed> $data */
    public function targetUrl(array $data, User $viewer): string
    {
        $targetId = filter_var($data['target_id'] ?? null, FILTER_VALIDATE_INT);

        if (($data['target_type'] ?? null) === 'conversation' && $targetId !== false) {
            $conversation = Conversation::query()->forMember($viewer)->find($targetId);

            if ($conversation !== null) {
                return route('conversations.show', $conversation, absolute: false);
            }
        }

        if (($data['target_type'] ?? null) === 'event'
            && $targetId !== false
            && Route::has('events.show')) {
            $event = Event::query()->with('organizer')->find($targetId);

            if ($event !== null && $viewer->can('view', $event)) {
                return route('events.show', $event, absolute: false);
            }
        }

        return route('notifications.index', absolute: false);
    }
}

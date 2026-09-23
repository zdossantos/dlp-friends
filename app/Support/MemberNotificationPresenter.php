<?php

namespace App\Support;

use App\Enums\RoleName;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\PartnerAnnouncementDelivery;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Route;
use LogicException;

final class MemberNotificationPresenter
{
    /** @return array{id: string, category: string, translation_key: string, parameters: array<string, string|int|null>, target_url: string, read_at: string|null, created_at: string|null, dismiss_url?: string, action_label?: string, content?: string} */
    public function present(DatabaseNotification $notification, User $viewer): array
    {
        $data = $notification->data;
        $partnerDelivery = $this->partnerDelivery($notification, $viewer);

        $presented = [
            'id' => $notification->id,
            'category' => (string) ($data['category'] ?? ''),
            'translation_key' => (string) ($data['translation_key'] ?? ''),
            'parameters' => is_array($data['parameters'] ?? null) ? $data['parameters'] : [],
            'target_url' => $this->targetUrl($data, $viewer, $notification, $partnerDelivery),
            'read_at' => $notification->read_at?->toISOString(),
            'created_at' => $notification->created_at?->toISOString(),
        ];

        if ($partnerDelivery !== null) {
            $actionLabel = __('notifications.actions.open_partner_announcement');

            if (! is_string($actionLabel)) {
                throw new LogicException('The partner announcement action label must be a string.');
            }

            $presented['dismiss_url'] = route(
                'notifications.partner-announcements.dismiss',
                $notification,
                absolute: false,
            );
            $presented['action_label'] = $actionLabel;
            $presented['content'] = $partnerDelivery->announcement_content;
        }

        return $presented;
    }

    /** @param array<string, mixed> $data */
    public function targetUrl(
        array $data,
        User $viewer,
        ?DatabaseNotification $notification = null,
        ?PartnerAnnouncementDelivery $partnerDelivery = null,
    ): string {
        if (($data['target_type'] ?? null) === 'partner_announcement' && $notification !== null) {
            $partnerDelivery ??= $this->partnerDelivery($notification, $viewer);

            if ($partnerDelivery !== null) {
                return route(
                    'partner-announcements.click',
                    $partnerDelivery->click_token,
                    absolute: false,
                );
            }
        }

        if (($data['target_type'] ?? null) === 'partner_announcement_management'
            && $viewer->hasRole(RoleName::Partner)) {
            return route('partner.announcements.index', absolute: false);
        }

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

    private function partnerDelivery(
        DatabaseNotification $notification,
        User $viewer,
    ): ?PartnerAnnouncementDelivery {
        if (($notification->data['target_type'] ?? null) !== 'partner_announcement') {
            return null;
        }

        return PartnerAnnouncementDelivery::query()
            ->where('notification_id', $notification->id)
            ->where('user_id', $viewer->id)
            ->first();
    }
}

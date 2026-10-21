<?php

namespace App\Notifications;

use App\Contracts\WebPushNotification;
use App\Enums\NotificationCategory;
use App\Enums\TripNotificationType;
use App\Enums\TripRegistrationStatus;
use App\Enums\UserStatus;
use App\Enums\WebPushPreference;
use App\Models\Trip;
use App\Models\User;
use App\Notifications\Channels\GroupMessageBroadcastChannel;
use App\Notifications\Channels\GroupMessageDatabaseChannel;
use App\Notifications\Channels\WebPushChannel;
use App\Policies\TripPolicy;
use App\Support\WebPushTarget;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Gate;

final class TripLifecycleNotification extends Notification implements ShouldQueue, WebPushNotification
{
    use Queueable;

    public function __construct(public int $tripId, public TripNotificationType $type, public ?int $registrationId = null)
    {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return [GroupMessageDatabaseChannel::class, GroupMessageBroadcastChannel::class, WebPushChannel::class];
    }

    public function shouldSend(User $notifiable, string $channel): bool
    {
        $recipient = $notifiable->fresh();

        return $recipient !== null && $this->webPushAccessAllowed($recipient)
            && ($recipient->notificationPreferences()->where('category', WebPushPreference::Trips)->value('enabled') ?? true);
    }

    /** @return array<string, mixed> */
    public function toArray(User $notifiable): array
    {
        return ['category' => NotificationCategory::Trips->value,
            'translation_key' => 'notifications.items.trip_'.$this->type->value,
            'parameters' => [], 'target_type' => 'trip', 'target_id' => $this->tripId];
    }

    public function webPushPreference(): WebPushPreference
    {
        return WebPushPreference::Trips;
    }

    public function webPushTarget(User $notifiable): WebPushTarget
    {
        return new WebPushTarget('/trips/'.$this->tripId);
    }

    public function webPushAccessAllowed(User $notifiable): bool
    {
        $recipient = $notifiable->fresh();
        $trip = Trip::query()->with('organizer')->find($this->tripId);
        if ($recipient === null || $trip === null || ! app(TripPolicy::class)->create($recipient)
            || $trip->organizer?->status !== UserStatus::Active
            || $recipient->blocksCreated()->where('blocked_user_id', $trip->organizer_user_id)->exists()
            || $recipient->blocksReceived()->where('blocker_user_id', $trip->organizer_user_id)->exists()) {
            return false;
        }
        $registration = $this->registrationId === null ? null : $trip->registrations()->find($this->registrationId);

        if ($registration !== null && $registration->user?->status !== UserStatus::Active) {
            return false;
        }

        return match ($this->type) {
            TripNotificationType::Requested, TripNotificationType::Withdrawn => $trip->organizer_user_id === $recipient->id
                && $registration !== null && $registration->status === ($this->type === TripNotificationType::Requested ? TripRegistrationStatus::Pending : TripRegistrationStatus::Withdrawn),
            TripNotificationType::Accepted, TripNotificationType::Refused, TripNotificationType::Removed => $registration !== null
                && $registration->user_id === $recipient->id && $registration->status->value === $this->type->value,
            TripNotificationType::Changed => Gate::forUser($recipient)->allows('viewPrivate', $trip),
            TripNotificationType::Cancelled => $trip->cancelled_at !== null && $trip->registrations()->where('user_id', $recipient->id)->whereIn('status', [TripRegistrationStatus::Accepted, TripRegistrationStatus::Pending])->exists(),
        };
    }
}

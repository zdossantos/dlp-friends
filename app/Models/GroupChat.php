<?php

namespace App\Models;

use App\Enums\EventRegistrationStatus;
use App\Enums\RoleName;
use App\Enums\TripRegistrationStatus;
use App\Enums\UserStatus;
use Database\Factories\GroupChatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $event_id
 * @property int|null $trip_id
 * @property-read Event|null $event
 * @property-read Trip|null $trip
 * @property-read Collection<int, GroupChatMessage> $messages
 * @property-read Collection<int, GroupChatRead> $reads
 */
#[Fillable(['event_id', 'trip_id'])]
class GroupChat extends Model
{
    /** @use HasFactory<GroupChatFactory> */
    use HasFactory;

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<Trip, $this> */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /** @return HasMany<GroupChatMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(GroupChatMessage::class);
    }

    /** @return HasMany<GroupChatRead, $this> */
    public function reads(): HasMany
    {
        return $this->hasMany(GroupChatRead::class);
    }

    /** @param Builder<GroupChat> $query */
    public function scopeVisibleTo(Builder $query, User $viewer): void
    {
        if ($viewer->status !== UserStatus::Active || ! $viewer->hasVerifiedEmail() || ! $viewer->hasRole(RoleName::User)) {
            $query->whereRaw('1 = 0');

            return;
        }
        $query->where(function (Builder $contexts) use ($viewer): void {
            $contexts->where(function (Builder $events) use ($viewer): void {
                $events->whereNull('trip_id')->whereHas('event', fn (Builder $event) => $event
                    ->whereHas('organizer', fn (Builder $owner) => $owner->where('status', UserStatus::Active)->whereNotNull('email_verified_at')->whereHas('roles', fn (Builder $roles) => $roles->where('name', RoleName::User)))
                    ->where(fn (Builder $membership) => $membership->where('organizer_user_id', $viewer->id)
                        ->orWhereHas('registrations', fn (Builder $registrations) => $registrations->where('user_id', $viewer->id)->where('status', EventRegistrationStatus::Accepted))));
            })->orWhereIn('trip_id', Trip::query()->visibleTo($viewer)->select('id')
                ->where(fn (Builder $membership) => $membership->where('organizer_user_id', $viewer->id)
                    ->orWhereHas('registrations', fn (Builder $registrations) => $registrations->where('user_id', $viewer->id)->where('status', TripRegistrationStatus::Accepted))));
        });
    }

    /** @param Builder<GroupChat> $query */
    public function scopeWithUnreadCount(Builder $query, User $viewer): void
    {
        $query->withCount(['messages as unread_count' => fn (Builder $messages) => $messages
            ->where('author_user_id', '!=', $viewer->id)
            ->whereRaw('group_chat_messages.id > COALESCE((SELECT last_read_message_id FROM group_chat_reads WHERE group_chat_reads.group_chat_id = group_chats.id AND user_id = ?), 0)', [$viewer->id])]);
    }

    public function isReadOnly(): bool
    {
        return $this->readOnlyReason() !== null;
    }

    public function readOnlyReason(Event|Trip|null $context = null): ?string
    {
        $context ??= $this->trip()->first() ?? $this->event()->first();
        if ($context === null || $context->cancelled_at !== null) {
            return 'cancelled';
        }

        $expires = $context instanceof Trip ? $context->departs_at->addHours(24) : $context->starts_at->addDays(7);

        return now()->greaterThanOrEqualTo($expires) ? 'expired' : null;
    }
}

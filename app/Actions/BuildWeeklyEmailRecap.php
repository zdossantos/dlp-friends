<?php

namespace App\Actions;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Conversation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class BuildWeeklyEmailRecap
{
    /** @return array{conversations: int, matches: int|null}|null */
    public function handle(User $user, CarbonImmutable $periodEnd): ?array
    {
        if (! $user->weekly_recap_messages || $user->status !== UserStatus::Active
            || $user->deletion_requested_at !== null || $user->email_verified_at === null
            || ! $user->hasRole(RoleName::User) || filter_var($user->email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        $visible = Conversation::query()->forMember($user)->withVisibleParticipant($user)->whereNull('archived_at');
        $unread = fn (Builder $messages) => $messages->whereNull('read_at')->where('author_user_id', '!=', $user->id);
        if (! (clone $visible)->whereHas('messages', fn (Builder $messages) => $unread($messages)
            ->where('created_at', '<', $periodEnd->subDays(3)))->exists()) {
            return null;
        }

        $periodStart = $periodEnd->setTimezone('Europe/Paris')->subWeek()->utc();

        return [
            'conversations' => (clone $visible)->whereHas('messages', $unread)->count(),
            'matches' => $user->weekly_recap_matches
                ? (clone $visible)->whereHas('memberMatch', fn (Builder $match) => $match
                    ->where('created_at', '>=', $periodStart)->where('created_at', '<', $periodEnd))->count()
                : null,
        ];
    }
}

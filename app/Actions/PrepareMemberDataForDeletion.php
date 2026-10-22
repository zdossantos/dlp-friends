<?php

namespace App\Actions;

use App\Enums\UserStatus;
use App\Models\User;
use App\Support\TripRetentionAccounts;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class PrepareMemberDataForDeletion
{
    /** @return Collection<int, User> */
    public function lockAccounts(User $member): Collection
    {
        // Include every endpoint that could lose data through a user cascade.
        $ids = collect([$member->id]);
        foreach (['matches' => ['user_low_id', 'user_high_id'], 'swipes' => ['actor_user_id', 'target_user_id'], 'blocks' => ['blocker_user_id', 'blocked_user_id'], 'conversation_reports' => ['reporter_user_id', 'target_user_id']] as $table => [$first, $second]) {
            $rows = DB::table($table)->where(fn ($q) => $q->where($first, $member->id)->orWhere($second, $member->id))->get();
            $ids = $ids->concat($rows->flatMap(fn ($row): array => [$row->{$first}, $row->{$second}]));
        }
        $eventIds = DB::table('events')->where('organizer_user_id', $member->id)->pluck('id');
        $chatIds = DB::table('group_chats')->whereIn('event_id', $eventIds)->pluck('id');
        $ids = $ids->concat(DB::table('event_registrations')->whereIn('event_id', $eventIds)->pluck('user_id'))
            ->concat(DB::table('group_chat_messages')->whereIn('group_chat_id', $chatIds)->pluck('author_user_id'))
            ->concat(DB::table('group_chat_reads')->whereIn('group_chat_id', $chatIds)->pluck('user_id'));
        $messageIds = DB::table('messages')->where('author_user_id', $member->id)->pluck('id');
        $groupMessageIds = DB::table('group_chat_messages')->where('author_user_id', $member->id)->pluck('id');
        $ids = $ids->concat(DB::table('message_reactions')->whereIn('message_id', $messageIds)->pluck('user_id'))
            ->concat(DB::table('group_chat_message_reactions')->whereIn('group_chat_message_id', $groupMessageIds)->pluck('user_id'));
        $tripIds = DB::table('trips')->where('organizer_user_id', $member->id)->pluck('id')
            ->concat(DB::table('trip_registrations')->where('user_id', $member->id)->pluck('trip_id'));
        $reports = DB::table('trip_departure_reports')->where('reporter_user_id', $member->id)->orWhere('target_user_id', $member->id)->get();
        $ids = $ids->concat(app(TripRetentionAccounts::class)->forTrips(array_values($tripIds->unique()->map(fn ($id): int => (int) $id)->all())))
            ->concat($reports->flatMap(fn ($report): array => [$report->reporter_user_id, $report->target_user_id, $report->decided_by_user_id]));

        // Locking reads see current committed statuses, unlike an earlier MySQL snapshot.
        return User::query()->whereKey($ids->filter()->unique()->sort()->values())->orderBy('id')->lockForUpdate()->get();
    }

    public function handle(User $member): void
    {
        $participants = $this->lockAccounts($member);
        $messageIds = DB::table('messages')->where('author_user_id', $member->id)->pluck('id');
        $groupMessageIds = DB::table('group_chat_messages')->where('author_user_id', $member->id)->pluck('id');
        $banned = $participants->filter(fn (User $user): bool => $user->status === UserStatus::Banned && $user->id !== $member->id)->pluck('id');
        DB::table('message_reactions')->whereIn('message_id', $messageIds)->whereNotIn('user_id', $banned)->delete();
        DB::table('group_chat_message_reactions')->whereIn('group_chat_message_id', $groupMessageIds)->whereNotIn('user_id', $banned)->delete();
        foreach (['matches' => ['user_low_id', 'user_high_id'], 'swipes' => ['actor_user_id', 'target_user_id'], 'blocks' => ['blocker_user_id', 'blocked_user_id']] as $table => [$first, $second]) {
            DB::table($table)->where(fn ($q) => $q->where($first, $member->id)->orWhere($second, $member->id))->where(fn ($q) => $q->whereNull($first)->orWhereNotIn($first, $banned))->where(fn ($q) => $q->whereNull($second)->orWhereNotIn($second, $banned))->delete();
        }
        $matchIds = DB::table('matches')->where('user_low_id', $member->id)->orWhere('user_high_id', $member->id)->pluck('id');
        DB::table('conversations')->whereIn('match_id', $matchIds)->update(['archived_at' => now()]);
        $events = DB::table('events')->where('organizer_user_id', $member->id)->orderBy('id')->lockForUpdate()->get();
        foreach ($events as $event) {
            $protected = DB::table('event_registrations')->where('event_id', $event->id)->whereIn('user_id', $banned)->exists()
                || DB::table('group_chat_messages')->join('group_chats', 'group_chats.id', '=', 'group_chat_messages.group_chat_id')->where('group_chats.event_id', $event->id)->whereIn('author_user_id', $banned)->exists()
                || DB::table('group_chat_message_reactions')->join('group_chat_messages', 'group_chat_messages.id', '=', 'group_chat_message_reactions.group_chat_message_id')->join('group_chats', 'group_chats.id', '=', 'group_chat_messages.group_chat_id')->where('group_chats.event_id', $event->id)->whereIn('group_chat_message_reactions.user_id', $banned)->exists()
                || DB::table('group_chat_reads')->join('group_chats', 'group_chats.id', '=', 'group_chat_reads.group_chat_id')->where('group_chats.event_id', $event->id)->whereIn('user_id', $banned)->exists();
            if ($protected) {
                DB::table('events')->where('id', $event->id)->update(['cancelled_at' => $event->cancelled_at ?? now()]);
            } else {
                DB::table('events')->where('id', $event->id)->delete();
            }
        }
        $tripIds = DB::table('trips')->where('organizer_user_id', $member->id)->pluck('id')
            ->concat(DB::table('trip_registrations')->where('user_id', $member->id)->pluck('trip_id'))->unique();
        $trips = DB::table('trips')->whereIn('id', $tripIds)->orderBy('id')->lockForUpdate()->get();
        foreach ($trips as $trip) {
            $protected = collect(app(TripRetentionAccounts::class)->forTrips([$trip->id]))->intersect($banned)->isNotEmpty();
            if ($trip->organizer_user_id === $member->id) {
                if ($protected) {
                    DB::table('trips')->where('id', $trip->id)->update(['cancelled_at' => $trip->cancelled_at ?? now()]);
                } else {
                    DB::table('trips')->where('id', $trip->id)->delete();
                }
            } elseif (! $protected) {
                DB::table('trip_registrations')->where('trip_id', $trip->id)->where('user_id', $member->id)->delete();
            }
        }
        DB::table('trip_departure_reports')->where(fn ($q) => $q->where('reporter_user_id', $member->id)->orWhere('target_user_id', $member->id))
            ->where(fn ($q) => $q->whereNull('reporter_user_id')->orWhereNotIn('reporter_user_id', $banned))
            ->where(fn ($q) => $q->whereNull('target_user_id')->orWhereNotIn('target_user_id', $banned))->delete();
        DB::table('conversation_reports')->where(fn ($q) => $q->where('reporter_user_id', $member->id)->orWhere('target_user_id', $member->id))->where(fn ($q) => $q->whereNull('reporter_user_id')->orWhereNotIn('reporter_user_id', $banned))->where(fn ($q) => $q->whereNull('target_user_id')->orWhereNotIn('target_user_id', $banned))->delete();
        DB::table('moderation_audits')->where('target_user_id', $member->id)->delete();
    }
}

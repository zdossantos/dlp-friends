<?php

namespace App\Actions;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PrepareMemberDataForDeletion
{
    public function handle(User $member): void
    {
        // Include every endpoint that could lose data through a user cascade.
        $ids = collect([$member->id]);
        foreach (['matches' => ['user_low_id', 'user_high_id'], 'swipes' => ['actor_user_id', 'target_user_id'], 'blocks' => ['blocker_user_id', 'blocked_user_id'], 'conversation_reports' => ['reporter_user_id', 'target_user_id']] as $table => [$first, $second]) {
            $rows = DB::table($table)->where(fn ($q) => $q->where($first, $member->id)->orWhere($second, $member->id))->get();
            $ids = $ids->concat($rows->flatMap(fn ($row): array => [$row->{$first}, $row->{$second}]));
        }
        $eventIds = DB::table('events')->where('organizer_user_id', $member->id)->pluck('id');
        $chatIds = DB::table('event_chats')->whereIn('event_id', $eventIds)->pluck('id');
        $ids = $ids->concat(DB::table('event_registrations')->whereIn('event_id', $eventIds)->pluck('user_id'))
            ->concat(DB::table('event_chat_messages')->whereIn('event_chat_id', $chatIds)->pluck('author_user_id'))
            ->concat(DB::table('event_chat_reads')->whereIn('event_chat_id', $chatIds)->pluck('user_id'));
        $messageIds = DB::table('messages')->where('author_user_id', $member->id)->pluck('id');
        $groupMessageIds = DB::table('event_chat_messages')->where('author_user_id', $member->id)->pluck('id');
        $ids = $ids->concat(DB::table('message_reactions')->whereIn('message_id', $messageIds)->pluck('user_id'))
            ->concat(DB::table('event_chat_message_reactions')->whereIn('event_chat_message_id', $groupMessageIds)->pluck('user_id'));
        User::query()->whereKey($ids->filter()->unique()->sort()->values())->orderBy('id')->lockForUpdate()->get();
        $banned = User::query()->whereKey($ids->filter()->unique())->where('status', UserStatus::Banned)->where('id', '!=', $member->id)->pluck('id');
        DB::table('message_reactions')->whereIn('message_id', $messageIds)->whereNotIn('user_id', $banned)->delete();
        DB::table('event_chat_message_reactions')->whereIn('event_chat_message_id', $groupMessageIds)->whereNotIn('user_id', $banned)->delete();
        foreach (['matches' => ['user_low_id', 'user_high_id'], 'swipes' => ['actor_user_id', 'target_user_id'], 'blocks' => ['blocker_user_id', 'blocked_user_id']] as $table => [$first, $second]) {
            DB::table($table)->where(fn ($q) => $q->where($first, $member->id)->orWhere($second, $member->id))->where(fn ($q) => $q->whereNull($first)->orWhereNotIn($first, $banned))->where(fn ($q) => $q->whereNull($second)->orWhereNotIn($second, $banned))->delete();
        }
        $matchIds = DB::table('matches')->where('user_low_id', $member->id)->orWhere('user_high_id', $member->id)->pluck('id');
        DB::table('conversations')->whereIn('match_id', $matchIds)->update(['archived_at' => now()]);
        $events = DB::table('events')->where('organizer_user_id', $member->id)->orderBy('id')->lockForUpdate()->get();
        foreach ($events as $event) {
            $protected = DB::table('event_registrations')->where('event_id', $event->id)->whereIn('user_id', $banned)->exists()
                || DB::table('event_chat_messages')->join('event_chats', 'event_chats.id', '=', 'event_chat_messages.event_chat_id')->where('event_chats.event_id', $event->id)->whereIn('author_user_id', $banned)->exists()
                || DB::table('event_chat_message_reactions')->join('event_chat_messages', 'event_chat_messages.id', '=', 'event_chat_message_reactions.event_chat_message_id')->join('event_chats', 'event_chats.id', '=', 'event_chat_messages.event_chat_id')->where('event_chats.event_id', $event->id)->whereIn('event_chat_message_reactions.user_id', $banned)->exists()
                || DB::table('event_chat_reads')->join('event_chats', 'event_chats.id', '=', 'event_chat_reads.event_chat_id')->where('event_chats.event_id', $event->id)->whereIn('user_id', $banned)->exists();
            if ($protected) {
                DB::table('events')->where('id', $event->id)->update(['cancelled_at' => $event->cancelled_at ?? now()]);
            } else {
                DB::table('events')->where('id', $event->id)->delete();
            }
        }
        DB::table('conversation_reports')->where(fn ($q) => $q->where('reporter_user_id', $member->id)->orWhere('target_user_id', $member->id))->where(fn ($q) => $q->whereNull('reporter_user_id')->orWhereNotIn('reporter_user_id', $banned))->where(fn ($q) => $q->whereNull('target_user_id')->orWhereNotIn('target_user_id', $banned))->delete();
        DB::table('moderation_audits')->where('target_user_id', $member->id)->delete();
    }
}

<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** The same retention graph protects scheduled purges and account deletion. */
final class TripRetentionAccounts
{
    /** @param list<int> $tripIds
     * @return list<int>
     */
    public function forTrips(array $tripIds): array
    {
        $chatIds = DB::table('group_chats')->whereIn('trip_id', $tripIds)->pluck('id');
        $messageIds = DB::table('group_chat_messages')->whereIn('group_chat_id', $chatIds)->pluck('id');
        $departureIds = DB::table('trip_departures')->whereIn('trip_id', $tripIds)->pluck('id');
        $reports = DB::table('trip_departure_reports')->whereIn('trip_departure_id', $departureIds)->get();

        return array_values(DB::table('trips')->whereIn('id', $tripIds)->pluck('organizer_user_id')
            ->concat(DB::table('trip_registrations')->whereIn('trip_id', $tripIds)->pluck('user_id'))
            ->concat(DB::table('trip_departures')->whereIn('trip_id', $tripIds)->pluck('user_id'))
            ->concat(DB::table('group_chat_messages')->whereIn('id', $messageIds)->pluck('author_user_id'))
            ->concat(DB::table('group_chat_reads')->whereIn('group_chat_id', $chatIds)->pluck('user_id'))
            ->concat(DB::table('group_chat_message_reactions')->whereIn('group_chat_message_id', $messageIds)->pluck('user_id'))
            ->concat($reports->flatMap(fn ($report): array => [$report->reporter_user_id, $report->target_user_id, $report->decided_by_user_id]))
            ->filter()->map(fn ($id): int => (int) $id)->unique()->sort()->all());
    }
}

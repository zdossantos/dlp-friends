<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['event_chats' => 'group_chats', 'event_chat_messages' => 'group_chat_messages', 'event_chat_reads' => 'group_chat_reads', 'event_chat_message_reactions' => 'group_chat_message_reactions'] as $old => $new) {
            Schema::rename($old, $new);
        }
        foreach (['group_chat_messages', 'group_chat_reads'] as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->renameColumn('event_chat_id', 'group_chat_id'));
        }
        Schema::table('group_chat_message_reactions', fn (Blueprint $table) => $table->renameColumn('event_chat_message_id', 'group_chat_message_id'));
        Schema::table('group_chats', function (Blueprint $table): void {
            $table->foreignId('event_id')->nullable()->change();
            $table->foreignId('trip_id')->nullable()->unique()->constrained()->cascadeOnDelete();
        });
        DB::statement('ALTER TABLE group_chats ADD CONSTRAINT group_chats_one_context CHECK ((event_id IS NULL) <> (trip_id IS NULL))');
        DB::table('trips')->select('id')->orderBy('id')->chunkById(500, function ($trips): void {
            DB::table('group_chats')->insertOrIgnore($trips->map(fn ($trip) => ['trip_id' => $trip->id, 'created_at' => now(), 'updated_at' => now()])->all());
        });
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE group_chats DROP CHECK group_chats_one_context');
        DB::table('group_chats')->whereNotNull('trip_id')->delete();
        Schema::table('group_chats', function (Blueprint $table): void {
            $table->dropForeign(['trip_id']);
            $table->dropColumn('trip_id');
            $table->foreignId('event_id')->nullable(false)->change();
        });
        Schema::table('group_chat_message_reactions', fn (Blueprint $table) => $table->renameColumn('group_chat_message_id', 'event_chat_message_id'));
        foreach (['group_chat_messages', 'group_chat_reads'] as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->renameColumn('group_chat_id', 'event_chat_id'));
        }
        foreach (['group_chat_message_reactions' => 'event_chat_message_reactions', 'group_chat_reads' => 'event_chat_reads', 'group_chat_messages' => 'event_chat_messages', 'group_chats' => 'event_chats'] as $old => $new) {
            Schema::rename($old, $new);
        }
    }
};

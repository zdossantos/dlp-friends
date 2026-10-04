<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, list<string>> */
    private array $references = ['matches' => ['user_low_id', 'user_high_id'], 'swipes' => ['actor_user_id', 'target_user_id'], 'blocks' => ['blocker_user_id', 'blocked_user_id'], 'events' => ['organizer_user_id']];

    public function up(): void
    {
        foreach (['message_reactions' => 'message_id', 'event_chat_message_reactions' => 'event_chat_message_id'] as $table => $column) {
            Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                $blueprint->dropForeign([$column]);
                $blueprint->foreignId($column)->nullable()->change();
                $blueprint->foreign($column)->references('id')->on($column === 'message_id' ? 'messages' : 'event_chat_messages')->nullOnDelete();
            });
        }
        foreach ($this->references as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                foreach ($columns as $column) {
                    $blueprint->dropForeign([$column]);
                    $blueprint->foreignId($column)->nullable()->change();
                    $blueprint->foreign($column)->references('id')->on('users')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['message_reactions' => 'message_id', 'event_chat_message_reactions' => 'event_chat_message_id'] as $table => $column) {
            DB::table($table)->whereNull($column)->delete();
            Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                $blueprint->dropForeign([$column]);
                $blueprint->foreignId($column)->nullable(false)->change();
                $blueprint->foreign($column)->references('id')->on($column === 'message_id' ? 'messages' : 'event_chat_messages')->cascadeOnDelete();
            });
        }
        // Detached retained graphs must be removed before restoring required references.
        foreach ($this->references as $table => $columns) {
            foreach ($columns as $column) {
                DB::table($table)->whereNull($column)->delete();
            }
            Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                foreach ($columns as $column) {
                    $blueprint->dropForeign([$column]);
                    $blueprint->foreignId($column)->nullable(false)->change();
                    $blueprint->foreign($column)->references('id')->on('users')->cascadeOnDelete();
                }
            });
        }
    }
};

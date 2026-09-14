<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique('event_id');
        });

        Schema::create('event_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_chat_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('content');
            $table->timestamps();
            $table->index(['event_chat_id', 'id']);
        });

        Schema::create('event_chat_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_chat_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('last_read_message_id')
                ->nullable()
                ->constrained('event_chat_messages')
                ->nullOnDelete();
            $table->timestamps();
            $table->unique(['event_chat_id', 'user_id']);
        });

        $timestamp = now();

        DB::table('events')
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function ($events) use ($timestamp): void {
                DB::table('event_chats')->insertOrIgnore(
                    $events->map(fn ($event): array => [
                        'event_id' => $event->id,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ])->all(),
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_chat_reads');
        Schema::dropIfExists('event_chat_messages');
        Schema::dropIfExists('event_chats');
    }
};

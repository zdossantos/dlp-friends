<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_chat_message_reactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_chat_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(
                ['event_chat_message_id', 'user_id'],
                'event_chat_message_reactions_message_user_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_chat_message_reactions');
    }
};

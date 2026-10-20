<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_chat_messages', function (Blueprint $table): void {
            $table->string('system_type', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_chat_messages', function (Blueprint $table): void {
            $table->dropColumn('system_type');
        });
    }
};

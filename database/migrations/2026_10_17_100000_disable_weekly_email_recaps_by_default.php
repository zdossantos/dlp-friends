<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('weekly_recap_messages')->default(false)->change();
            $table->boolean('weekly_recap_matches')->default(false)->change();
        });

        DB::table('users')->update([
            'weekly_recap_messages' => false,
            'weekly_recap_matches' => false,
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('weekly_recap_messages')->default(true)->change();
            $table->boolean('weekly_recap_matches')->default(true)->change();
        });
    }
};

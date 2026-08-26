<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->unique()->constrained('matches')->cascadeOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        $now = now();

        DB::table('conversations')->insertUsing(
            ['match_id', 'created_at', 'updated_at'],
            DB::table('matches')->select('id')->selectRaw(
                '? as created_at, ? as updated_at',
                [$now, $now],
            ),
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};

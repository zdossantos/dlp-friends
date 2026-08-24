<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interest_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('max_selections')->default(5);
            $table->timestamps();
        });

        DB::table('interest_settings')->insert([
            'id' => 1,
            'max_selections' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('interest_settings');
    }
};

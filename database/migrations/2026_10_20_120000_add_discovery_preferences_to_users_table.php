<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedTinyInteger('discovery_min_age')->nullable();
            $table->unsignedTinyInteger('discovery_max_age')->nullable();
            $table->unsignedTinyInteger('discovery_min_common_interests')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['discovery_min_age', 'discovery_max_age', 'discovery_min_common_interests']);
        });
    }
};

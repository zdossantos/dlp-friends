<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_onboardings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 24);
            $table->string('step', 32)->nullable();
            $table->timestamps();
        });

        Schema::create('product_onboarding_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->foreignId('pass_avatar_id')->constrained('avatars')->restrictOnDelete();
            $table->foreignId('like_avatar_id')->constrained('avatars')->restrictOnDelete();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE product_onboarding_settings ADD CONSTRAINT product_onboarding_distinct_avatars CHECK (pass_avatar_id <> like_avatar_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('product_onboarding_settings');
        Schema::dropIfExists('product_onboardings');
    }
};

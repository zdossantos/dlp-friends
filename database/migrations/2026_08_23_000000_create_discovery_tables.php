<?php

use App\Enums\SwipeDecision;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passion_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('passions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('passion_category_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('passion_profile', function (Blueprint $table) {
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('passion_id')->constrained()->cascadeOnDelete();
            $table->unique(['profile_id', 'passion_id']);
        });

        Schema::create('swipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('decision', array_column(SwipeDecision::cases(), 'value'));
            $table->timestamps();
            $table->unique(['actor_user_id', 'target_user_id']);
        });

        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_low_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_high_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_low_id', 'user_high_id']);
        });

        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blocker_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('blocked_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['blocker_user_id', 'blocked_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocks');
        Schema::dropIfExists('matches');
        Schema::dropIfExists('swipes');
        Schema::dropIfExists('passion_profile');
        Schema::dropIfExists('passions');
        Schema::dropIfExists('passion_categories');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_email_recap_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('period_ends_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'period_ends_at'], 'weekly_recap_user_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_email_recap_deliveries');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_departure_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trip_departure_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reporter_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scheduled_departure_at');
            $table->timestamp('left_at');
            $table->text('details');
            $table->string('open_key')->nullable()->unique();
            $table->text('decision')->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable()->index();
            $table->timestamps();
        });
        Schema::table('moderation_audits', function (Blueprint $table): void {
            $table->foreignId('trip_departure_report_id')->nullable()->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('moderation_audits', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('trip_departure_report_id');
        });
        Schema::dropIfExists('trip_departure_reports');
    }
};

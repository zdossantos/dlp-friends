<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('transport', 20);
            $table->string('direction', 20);
            $table->string('general_departure', 160);
            $table->string('destination', 160);
            $table->dateTime('departs_at')->index();
            $table->string('meeting_point', 255);
            $table->string('audience', 20)->default('matches');
            $table->unsignedSmallInteger('car_seats')->nullable();
            $table->dateTime('first_accepted_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
        });
        Schema::create('trip_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->text('reason')->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['trip_id', 'user_id']);
        });
        Schema::create('trip_departures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('scheduled_departure_at');
            $table->dateTime('left_at');
            $table->text('reason');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_departures');
        Schema::dropIfExists('trip_registrations');
        Schema::dropIfExists('trips');
    }
};

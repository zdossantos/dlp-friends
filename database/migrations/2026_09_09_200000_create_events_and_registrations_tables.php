<?php

use App\Enums\EventRegistrationMode;
use App\Enums\EventRegistrationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 120);
            $table->text('description');
            $table->string('general_location', 160);
            $table->string('detailed_location', 255);
            $table->timestamp('starts_at');
            $table->unsignedSmallInteger('capacity');
            $table->enum('registration_mode', array_column(EventRegistrationMode::cases(), 'value'));
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['cancelled_at', 'starts_at']);
        });

        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', array_column(EventRegistrationStatus::cases(), 'value'));
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
            $table->index(['event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('events');
    }
};

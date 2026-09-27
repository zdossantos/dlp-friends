<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_push_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('notification_id');
            $table->foreignId('web_push_subscription_id')->constrained()->cascadeOnDelete();
            $table->string('category', 40);
            $table->string('status', 24)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('last_status_code')->nullable();
            $table->string('last_error', 255)->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->unique(['notification_id', 'web_push_subscription_id'], 'web_push_delivery_once');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_push_deliveries');
    }
};

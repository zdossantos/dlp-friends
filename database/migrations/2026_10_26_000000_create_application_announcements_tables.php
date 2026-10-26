<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_announcements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title_fr', 80);
            $table->string('title_en', 80);
            $table->text('content_fr');
            $table->text('content_en');
            $table->string('destination_url', 2048)->nullable();
            $table->boolean('is_release')->default(false);
            $table->boolean('send_push')->default(false);
            $table->string('audience', 16);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
        Schema::create('application_announcement_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_announcement_id')->constrained(indexName: 'application_delivery_announcement_foreign')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('notification_id')->nullable();
            $table->string('status', 16)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('broadcasted_at')->nullable();
            $table->timestamps();
            $table->unique(['application_announcement_id', 'user_id'], 'application_delivery_recipient_unique');
            $table->index(['application_announcement_id', 'status'], 'application_delivery_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_announcement_deliveries');
        Schema::dropIfExists('application_announcements');
    }
};

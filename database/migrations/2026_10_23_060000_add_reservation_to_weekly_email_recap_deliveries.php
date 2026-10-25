<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_email_recap_deliveries', function (Blueprint $table): void {
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('first_attempt_at')->nullable()->index();
            $table->uuid('idempotency_key')->nullable();
            $table->longText('resend_payload')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('weekly_email_recap_deliveries', function (Blueprint $table): void {
            $table->dropIndex(['first_attempt_at']);
            $table->dropColumn(['reserved_at', 'first_attempt_at', 'idempotency_key', 'resend_payload']);
        });
    }
};

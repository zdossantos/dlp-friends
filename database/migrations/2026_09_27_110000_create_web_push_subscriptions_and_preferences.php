<?php

use App\Enums\WebPushPreference;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_push_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            $table->char('endpoint_hash', 64)->unique();
            $table->text('p256dh');
            $table->text('auth');
            $table->string('content_encoding', 24)->default('aes128gcm');
            $table->string('device_name', 80)->nullable();
            $table->string('platform', 32)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 40);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'category']);
        });

        DB::table('partner_notification_preferences')->orderBy('id')->each(function (object $preference): void {
            DB::table('notification_preferences')->insert([
                'user_id' => $preference->user_id,
                'category' => WebPushPreference::PartnerAnnouncements->value,
                'enabled' => $preference->enabled,
                'created_at' => $preference->created_at,
                'updated_at' => $preference->updated_at,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('web_push_subscriptions');
    }
};

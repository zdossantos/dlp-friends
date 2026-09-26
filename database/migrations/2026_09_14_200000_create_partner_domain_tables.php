<?php

use App\Enums\PartnerAnnouncementStatus;
use App\Enums\PartnerDeliveryStatus;
use App\Enums\PartnerRevisionStatus;
use App\Enums\RoleAuditAction;
use App\Enums\RoleName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('published_revision_id')->nullable();
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('position')->default(0)->index();
            $table->timestamps();
        });

        Schema::create('partner_profile_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_profile_id')->constrained()->cascadeOnDelete();
            $table->string('name_fr', 100);
            $table->string('name_en', 100);
            $table->text('description_fr');
            $table->text('description_en');
            $table->string('image_path')->nullable();
            $table->enum('status', array_column(PartnerRevisionStatus::cases(), 'value'));
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->unsignedTinyInteger('draft_key')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['partner_profile_id', 'draft_key']);
            $table->index(['partner_profile_id', 'status']);
        });

        Schema::table('partner_profiles', function (Blueprint $table): void {
            $table->foreign('published_revision_id')
                ->references('id')
                ->on('partner_profile_revisions')
                ->nullOnDelete();
        });

        Schema::create('partner_announcements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_profile_id')->constrained()->cascadeOnDelete();
            $table->string('title', 80);
            $table->text('content');
            $table->string('destination_url', 2048);
            $table->enum('status', array_column(PartnerAnnouncementStatus::cases(), 'value'))->index();
            $table->uuid('run_uuid')->nullable()->unique();
            $table->timestamp('audience_prepared_at')->nullable();
            $table->timestamp('sending_started_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('partner_announcement_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('notification_id')->nullable()->constrained('notifications')->nullOnDelete();
            $table->char('click_token', 64)->unique();
            $table->enum('status', array_column(PartnerDeliveryStatus::cases(), 'value'))->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('last_error', 1000)->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamp('first_clicked_at')->nullable();
            $table->unsignedInteger('click_count')->default(0);
            $table->timestamps();
            $table->unique(
                ['partner_announcement_id', 'user_id'],
                'partner_deliveries_announcement_user_unique',
            );
        });

        Schema::create('partner_announcement_metrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_announcement_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('prepared_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('read_count')->default(0);
            $table->unsignedInteger('dismissed_count')->default(0);
            $table->unsignedInteger('unique_click_count')->default(0);
            $table->unsignedInteger('total_click_count')->default(0);
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('partner_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedSmallInteger('cooldown_days')->default(30);
            $table->timestamps();
        });

        Schema::create('partner_notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });

        Schema::create('role_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('role', array_column(RoleName::cases(), 'value'));
            $table->enum('action', array_column(RoleAuditAction::cases(), 'value'));
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        $now = now();

        DB::table('roles')->insertOrIgnore([
            'name' => RoleName::Partner->value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('partner_settings')->insertOrIgnore([
            'id' => 1,
            'cooldown_days' => 30,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('role_audits');
        Schema::dropIfExists('partner_notification_preferences');
        Schema::dropIfExists('partner_settings');
        Schema::dropIfExists('partner_announcement_metrics');
        Schema::dropIfExists('partner_announcement_deliveries');
        Schema::dropIfExists('partner_announcements');
        Schema::table('partner_profiles', function (Blueprint $table): void {
            $table->dropForeign(['published_revision_id']);
        });
        Schema::dropIfExists('partner_profile_revisions');
        Schema::dropIfExists('partner_profiles');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_announcement_deliveries', function (Blueprint $table): void {
            $table->unsignedBigInteger('source_announcement_id')
                ->nullable()
                ->after('partner_announcement_id');
            $table->string('announcement_title', 80)
                ->nullable()
                ->after('source_announcement_id');
            $table->text('announcement_content')
                ->nullable()
                ->after('announcement_title');
            $table->string('announcement_destination_url', 2048)
                ->nullable()
                ->after('announcement_content');
        });

        DB::table('partner_announcement_deliveries')
            ->orderBy('id')
            ->chunkById(500, function ($deliveries): void {
                $announcements = DB::table('partner_announcements')
                    ->whereIn('id', $deliveries->pluck('partner_announcement_id'))
                    ->get(['id', 'title', 'content', 'destination_url'])
                    ->keyBy('id');

                foreach ($deliveries as $delivery) {
                    $announcement = $announcements->get($delivery->partner_announcement_id);

                    DB::table('partner_announcement_deliveries')
                        ->where('id', $delivery->id)
                        ->update([
                            'source_announcement_id' => $delivery->partner_announcement_id,
                            'announcement_title' => $announcement->title,
                            'announcement_content' => $announcement->content,
                            'announcement_destination_url' => $announcement->destination_url,
                        ]);
                }
            });

        Schema::table('partner_announcement_deliveries', function (Blueprint $table): void {
            $table->dropForeign(['partner_announcement_id']);
            $table->foreignId('partner_announcement_id')->nullable()->change();
            $table->foreign('partner_announcement_id')
                ->references('id')
                ->on('partner_announcements')
                ->nullOnDelete();
            $table->unsignedBigInteger('source_announcement_id')->nullable(false)->change();
            $table->string('announcement_title', 80)->nullable(false)->change();
            $table->text('announcement_content')->nullable(false)->change();
            $table->string('announcement_destination_url', 2048)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('partner_announcement_deliveries', function (Blueprint $table): void {
            $table->dropForeign(['partner_announcement_id']);
        });

        DB::table('partner_announcement_deliveries')
            ->whereNull('partner_announcement_id')
            ->delete();

        Schema::table('partner_announcement_deliveries', function (Blueprint $table): void {
            $table->foreignId('partner_announcement_id')->nullable(false)->change();
            $table->foreign('partner_announcement_id')
                ->references('id')
                ->on('partner_announcements')
                ->cascadeOnDelete();
            $table->dropColumn([
                'source_announcement_id',
                'announcement_title',
                'announcement_content',
                'announcement_destination_url',
            ]);
        });
    }
};

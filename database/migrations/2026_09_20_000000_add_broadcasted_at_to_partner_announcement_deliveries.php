<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_announcement_deliveries', function (Blueprint $table): void {
            $table->timestamp('broadcasted_at')->nullable()->after('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::table('partner_announcement_deliveries', function (Blueprint $table): void {
            $table->dropColumn('broadcasted_at');
        });
    }
};

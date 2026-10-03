<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('self_registered_at')->nullable();
            $table->timestamp('new_member_announced_at')->nullable();
            $table->boolean('admin_new_member_alerts')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['self_registered_at', 'new_member_announced_at', 'admin_new_member_alerts']);
        });
    }
};

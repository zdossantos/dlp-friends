<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('locale', 2)->nullable()->after('email');
        });

        Schema::table('interests', function (Blueprint $table): void {
            $table->string('name_en', 80)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('interests', function (Blueprint $table): void {
            $table->dropColumn('name_en');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('locale');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('passion_categories', 'interest_categories');
        Schema::rename('passions', 'interests');
        Schema::rename('passion_profile', 'interest_profile');

        Schema::table('interests', function (Blueprint $table): void {
            $table->renameColumn('passion_category_id', 'interest_category_id');
        });
        Schema::table('interest_profile', function (Blueprint $table): void {
            $table->renameColumn('passion_id', 'interest_id');
            $table->boolean('is_selected')->default(true)->index();
        });

        DB::table('interest_profile')
            ->whereIn(
                'interest_id',
                DB::table('interests')
                    ->select('id')
                    ->where('is_active', false),
            )
            ->update(['is_selected' => false]);
    }

    public function down(): void
    {
        Schema::table('interest_profile', function (Blueprint $table): void {
            $table->dropIndex(['is_selected']);
            $table->dropColumn('is_selected');
            $table->renameColumn('interest_id', 'passion_id');
        });
        Schema::table('interests', function (Blueprint $table): void {
            $table->renameColumn('interest_category_id', 'passion_category_id');
        });
        Schema::rename('interest_profile', 'passion_profile');
        Schema::rename('interests', 'passions');
        Schema::rename('interest_categories', 'passion_categories');
    }
};

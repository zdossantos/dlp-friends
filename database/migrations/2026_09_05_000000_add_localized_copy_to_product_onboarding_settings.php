<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_onboarding_settings', function (Blueprint $table) {
            $table->string('pass_display_name', 80)->default('Camille')->after('like_avatar_id');
            $table->string('pass_display_name_en', 80)->nullable()->default('Camille')->after('pass_display_name');
            $table->string('pass_bio', 500)->default('Aime découvrir les détails du parc et profiter des spectacles.')->after('pass_display_name_en');
            $table->string('pass_bio_en', 500)->nullable()->default('Enjoys discovering park details and watching the shows.')->after('pass_bio');
            $table->string('like_display_name', 80)->default('Alex')->after('pass_bio_en');
            $table->string('like_display_name_en', 80)->nullable()->default('Alex')->after('like_display_name');
            $table->string('like_bio', 500)->default('Toujours partant pour partager une journée conviviale entre fans.')->after('like_display_name_en');
            $table->string('like_bio_en', 500)->nullable()->default('Always happy to share a park day with fellow fans.')->after('like_bio');
        });

        DB::table('product_onboarding_settings')->update([
            'pass_display_name_en' => 'Camille',
            'pass_bio_en' => 'Enjoys discovering park details and watching the shows.',
            'like_display_name_en' => 'Alex',
            'like_bio_en' => 'Always happy to share a park day with fellow fans.',
        ]);
    }

    public function down(): void
    {
        Schema::table('product_onboarding_settings', function (Blueprint $table) {
            $table->dropColumn([
                'pass_display_name',
                'pass_display_name_en',
                'pass_bio',
                'pass_bio_en',
                'like_display_name',
                'like_display_name_en',
                'like_bio',
                'like_bio_en',
            ]);
        });
    }
};

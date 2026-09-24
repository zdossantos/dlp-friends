<?php

use App\Enums\SeasonalThemeName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seasonal_themes', function (Blueprint $table): void {
            $table->id();
            $table->enum('theme', array_column(SeasonalThemeName::cases(), 'value'))->unique();
            $table->boolean('is_manually_active')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE seasonal_themes
            ADD CONSTRAINT seasonal_themes_valid_period CHECK (
                (starts_at IS NULL AND ends_at IS NULL)
                OR (starts_at IS NOT NULL AND ends_at IS NOT NULL AND ends_at > starts_at)
            )
            SQL);

        $now = now();
        DB::table('seasonal_themes')->insert(array_map(
            fn (SeasonalThemeName $theme): array => [
                'theme' => $theme->value,
                'is_manually_active' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            SeasonalThemeName::cases(),
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('seasonal_themes');
    }
};

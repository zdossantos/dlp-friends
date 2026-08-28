<?php

use App\Enums\ProductOnboardingStatus;
use App\Enums\ProductOnboardingStep;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('product_onboardings')
            ->where('status', 'skipped')
            ->update([
                'status' => ProductOnboardingStatus::InProgress->value,
                'step' => ProductOnboardingStep::PassDemo->value,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Normalized rows cannot be distinguished safely from genuine progress.
    }
};

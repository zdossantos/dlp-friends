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
            ->where('status', ProductOnboardingStatus::InProgress->value)
            ->whereNull('step')
            ->update(['step' => ProductOnboardingStep::InstallApp->value]);
    }

    public function down(): void
    {
        DB::table('product_onboardings')
            ->where('status', ProductOnboardingStatus::InProgress->value)
            ->where('step', ProductOnboardingStep::InstallApp->value)
            ->update(['step' => ProductOnboardingStep::ConversationDemo->value]);
    }
};

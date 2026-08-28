<?php

namespace App\Http\Controllers\Settings;

use App\Actions\AdvanceProductOnboarding;
use App\Enums\ProductOnboardingStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductOnboardingController extends Controller
{
    public function edit(Request $request): Response
    {
        $progress = $request->user()->productOnboarding()->first();

        return Inertia::render('settings/Onboarding', [
            'onboarding' => [
                'status' => $progress?->status->value ?? ProductOnboardingStatus::NotStarted->value,
                'step' => $progress?->step?->value,
                'updatedAt' => $progress?->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function restart(Request $request, AdvanceProductOnboarding $onboarding): RedirectResponse
    {
        $onboarding->start($request->user(), restart: true);

        return to_route('onboarding.show');
    }
}

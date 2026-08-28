<?php

namespace App\Http\Controllers;

use App\Enums\ProductOnboardingStatus;
use App\Enums\RoleName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LandingController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user()->loadMissing(['profile', 'productOnboarding', 'roles']);

        if (! $user->profile?->isComplete()) {
            return to_route('member-profile.create');
        }

        if ($user->productOnboarding === null
            || in_array($user->productOnboarding->status, [
                ProductOnboardingStatus::NotStarted,
                ProductOnboardingStatus::InProgress,
            ], true)) {
            return to_route('onboarding.show');
        }

        return $user->hasRole(RoleName::Admin)
            ? to_route('dashboard')
            : to_route('discovery.index');
    }
}

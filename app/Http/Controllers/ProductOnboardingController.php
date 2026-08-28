<?php

namespace App\Http\Controllers;

use App\Actions\AdvanceProductOnboarding;
use App\Enums\ProductOnboardingStep;
use App\Http\Requests\UpdateProductOnboardingRequest;
use App\Models\Avatar;
use App\Models\ProductOnboardingSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductOnboardingController extends Controller
{
    public function __construct(
        private readonly AdvanceProductOnboarding $onboarding,
    ) {}

    public function show(Request $request): Response
    {
        $settings = ProductOnboardingSetting::current();

        abort_unless($this->hasValidSettings($settings), 503, __('Le tutoriel est temporairement indisponible.'));

        $existingProgress = $request->user()->productOnboarding()->first();
        $progress = $this->onboarding->start($request->user());

        return Inertia::render('Onboarding/Show', [
            'status' => $progress->status->value,
            'step' => $progress->step?->value,
            'resumable' => $existingProgress?->is($progress) ?? false,
            'demoProfiles' => [
                $this->demoProfile($settings->passAvatar, 'pass'),
                $this->demoProfile($settings->likeAvatar, 'like'),
            ],
        ]);
    }

    public function advance(UpdateProductOnboardingRequest $request): RedirectResponse
    {
        $this->onboarding->advance($request->user(), $request->enum('step', ProductOnboardingStep::class));

        return to_route('onboarding.show');
    }

    public function complete(Request $request): RedirectResponse
    {
        $this->onboarding->complete($request->user());

        return to_route('discovery.index');
    }

    private function hasValidSettings(?ProductOnboardingSetting $settings): bool
    {
        return $settings !== null
            && $settings->pass_avatar_id !== $settings->like_avatar_id
            && $settings->passAvatar?->is_active === true
            && $settings->likeAvatar?->is_active === true;
    }

    /** @return array{displayName: string, bio: string, interests: array<int, string>, avatar: array{name: string, imageUrl: string, primaryColor: string, secondaryColor: string}} */
    private function demoProfile(Avatar $avatar, string $kind): array
    {
        /** @var array{display_name: string, bio: string, interests: array<int, string>} $copy */
        $copy = trans("frontend.onboarding.demo_profiles.{$kind}");

        return [
            'displayName' => $copy['display_name'],
            'bio' => $copy['bio'],
            'interests' => $copy['interests'],
            'avatar' => [
                'name' => $avatar->name,
                'imageUrl' => route('avatars.image', $avatar),
                'primaryColor' => $avatar->primary_color,
                'secondaryColor' => $avatar->secondary_color,
            ],
        ];
    }
}

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

        abort_unless($this->hasValidSettings($settings), 503, __('onboarding.unavailable'));

        $progress = $this->onboarding->start($request->user());

        return Inertia::render('Onboarding/Show', [
            'status' => $progress->status->value,
            'step' => $progress->step?->value,
            'demoProfiles' => [
                $this->demoProfile($settings, $settings->passAvatar, 'pass'),
                $this->demoProfile($settings, $settings->likeAvatar, 'like'),
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
    private function demoProfile(ProductOnboardingSetting $settings, Avatar $avatar, string $kind): array
    {
        /** @var array{interests: array<int, string>} $copy */
        $copy = trans("onboarding.demo_profiles.{$kind}");
        $displayName = $kind === 'pass' ? $settings->pass_display_name : $settings->like_display_name;
        $bio = $kind === 'pass' ? $settings->pass_bio : $settings->like_bio;

        if (app()->getLocale() === 'en') {
            $displayName = ($kind === 'pass' ? $settings->pass_display_name_en : $settings->like_display_name_en)
                ?: $displayName;
            $bio = ($kind === 'pass' ? $settings->pass_bio_en : $settings->like_bio_en)
                ?: $bio;
        }

        return [
            'displayName' => $displayName,
            'bio' => $bio,
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

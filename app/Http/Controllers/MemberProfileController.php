<?php

namespace App\Http\Controllers;

use App\Enums\ProfileVisibility;
use App\Enums\VisitFrequency;
use App\Http\Requests\MemberProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MemberProfileController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('profile/Create', [
            'profile' => $request->user()->profile,
            ...$this->formOptions(),
        ]);
    }

    public function store(MemberProfileRequest $request): RedirectResponse
    {
        $profile = $request->user()->profile()->updateOrCreate(
            [],
            [
                ...$request->validated(),
                'onboarding_completed_at' => $request->user()->profile?->onboarding_completed_at ?? now(),
            ],
        );

        Gate::authorize('update', $profile);

        return to_route('app');
    }

    public function show(Request $request): Response
    {
        return Inertia::render('profile/Show', [
            'profile' => $request->user()->profile,
            'age' => $request->user()->age,
        ]);
    }

    public function edit(Request $request): Response
    {
        return Inertia::render('profile/Edit', [
            'profile' => $request->user()->profile,
            ...$this->formOptions(),
        ]);
    }

    public function update(MemberProfileRequest $request): RedirectResponse
    {
        $profile = $request->user()->profile;

        Gate::authorize('update', $profile);
        $profile->update($request->validated());

        return to_route('member-profile.show');
    }

    /**
     * @return array{visitFrequencies: array<int, array{value: string, label: string}>, visibilities: array<int, array{value: string, label: string}>}
     */
    private function formOptions(): array
    {
        return [
            'visitFrequencies' => array_map(
                fn (VisitFrequency $frequency): array => [
                    'value' => $frequency->value,
                    'label' => $frequency->label(),
                ],
                VisitFrequency::cases(),
            ),
            'visibilities' => array_map(
                fn (ProfileVisibility $visibility): array => [
                    'value' => $visibility->value,
                    'label' => $visibility->label(),
                ],
                ProfileVisibility::cases(),
            ),
        ];
    }
}

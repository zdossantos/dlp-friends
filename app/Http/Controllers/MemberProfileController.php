<?php

namespace App\Http\Controllers;

use App\Actions\SyncProfileInterests;
use App\Enums\ProfileVisibility;
use App\Enums\VisitFrequency;
use App\Http\Requests\MemberProfileRequest;
use App\Models\Interest;
use App\Models\InterestSetting;
use App\Models\Profile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MemberProfileController extends Controller
{
    public function __construct(
        private readonly SyncProfileInterests $syncProfileInterests,
    ) {}

    public function create(Request $request): Response
    {
        return Inertia::render('profile/Create', [
            'profile' => $request->user()->profile,
            ...$this->formOptions($request->user()->profile),
        ]);
    }

    public function store(MemberProfileRequest $request): RedirectResponse
    {
        $completedAt = $request->user()->profile?->onboarding_completed_at;
        $validated = $request->validated();
        /** @var list<int> $interestIds */
        $interestIds = $validated['interest_ids'];
        unset($validated['interest_ids']);

        $profile = DB::transaction(function () use ($request, $completedAt, $validated, $interestIds): Profile {
            $profile = $request->user()->profile()->updateOrCreate(
                [],
                [
                    ...$validated,
                    'onboarding_completed_at' => $completedAt ?? now(),
                ],
            );

            Gate::authorize('update', $profile);
            $this->syncProfileInterests->handle($profile, $interestIds);

            return $profile;
        });
        $request->user()->setRelation('profile', $profile);

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
            ...$this->formOptions($request->user()->profile),
        ]);
    }

    public function update(MemberProfileRequest $request): RedirectResponse
    {
        $profile = $request->user()->profile;

        Gate::authorize('update', $profile);
        $validated = $request->validated();
        /** @var list<int> $interestIds */
        $interestIds = $validated['interest_ids'];
        unset($validated['interest_ids']);

        DB::transaction(function () use ($profile, $validated, $interestIds): void {
            $profile->update($validated);
            $this->syncProfileInterests->handle($profile, $interestIds);
        });

        return to_route('member-profile.show');
    }

    /**
     * @return array{
     *     visitFrequencies: array<int, array{value: string, label: string}>,
     *     visibilities: array<int, array{value: string, label: string}>,
     *     interests: array<int, array{id: int, name: string}>,
     *     selectedInterestIds: array<int, int>,
     *     interestLimit: int,
     * }
     */
    private function formOptions(?Profile $profile): array
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
            'interests' => Interest::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'name'])
                ->toArray(),
            'selectedInterestIds' => $profile?->interests()
                ->pluck('interests.id')
                ->all() ?? [],
            'interestLimit' => InterestSetting::current()->max_selections,
        ];
    }
}

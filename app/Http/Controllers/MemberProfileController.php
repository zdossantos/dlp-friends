<?php

namespace App\Http\Controllers;

use App\Actions\SyncProfileInterests;
use App\Enums\ProfileVisibility;
use App\Enums\VisitFrequency;
use App\Http\Requests\MemberProfileRequest;
use App\Models\Avatar;
use App\Models\Interest;
use App\Models\InterestSetting;
use App\Models\Profile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
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
            'canManageAvatars' => $request->user()->can('viewAny', Avatar::class),
            ...$this->formOptions($request->user()->profile),
        ]);
    }

    public function store(MemberProfileRequest $request): RedirectResponse
    {
        $currentProfile = $request->user()->profile;
        $completedAt = $currentProfile?->onboarding_completed_at;
        $validated = $request->validated();
        $interestIds = $request->interestIds();
        unset($validated['interest_ids']);

        $profile = DB::transaction(function () use ($request, $currentProfile, $completedAt, $validated, $interestIds): Profile {
            $this->lockActiveAvatar((int) $validated['avatar_id']);
            $profileFields = [
                ...$validated,
                'onboarding_completed_at' => $completedAt ?? now(),
            ];

            if ($currentProfile === null) {
                $profile = $request->user()->profile()->create($profileFields);

                Gate::authorize('update', $profile);
                $this->syncProfileInterests->handle($profile, $interestIds);

                return $profile;
            }

            Gate::authorize('update', $currentProfile);
            $this->syncProfileInterests->handle($currentProfile, $interestIds);
            $currentProfile->update($profileFields);

            return $currentProfile;
        });
        $request->user()->setRelation('profile', $profile);

        return to_route('app');
    }

    public function show(Request $request): Response
    {
        $profile = $request->user()->profile;

        return Inertia::render('profile/Show', [
            'profile' => $profile === null ? null : [
                'display_name' => $profile->display_name,
                'avatar' => $profile->avatar === null ? null : [
                    'id' => $profile->avatar->id,
                    'name' => $profile->avatar->name,
                    'image_url' => route('avatars.image', $profile->avatar),
                    'primary_color' => $profile->avatar->primary_color,
                    'secondary_color' => $profile->avatar->secondary_color,
                ],
                'bio' => $profile->bio,
                'visit_frequency' => $profile->visit_frequency,
                'visibility' => $profile->visibility,
                'onboarding_completed_at' => $profile->onboarding_completed_at,
                'interests' => $profile->interests()
                    ->orderBy('interests.sort_order')
                    ->orderBy('interests.id')
                    ->get(['interests.id', 'interests.name'])
                    ->map(fn (Interest $interest): array => [
                        'id' => $interest->id,
                        'name' => $interest->name,
                    ])
                    ->all(),
            ],
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
        $interestIds = $request->interestIds();
        unset($validated['interest_ids']);

        DB::transaction(function () use ($profile, $validated, $interestIds): void {
            $this->lockActiveAvatar((int) $validated['avatar_id']);
            $this->syncProfileInterests->handle($profile, $interestIds);
            $profile->update($validated);
        });

        return to_route('member-profile.show');
    }

    /**
     * @return array{
     *     visitFrequencies: array<int, array{value: string, label: string}>,
     *     visibilities: array<int, array{value: string, label: string}>,
     *     interests: array<int, array{id: int, name: string}>,
     *     avatars: array<int, array{id: int, name: string, image_url: string, primary_color: string, secondary_color: string}>,
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
            'avatars' => Avatar::query()
                ->active()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (Avatar $avatar): array => [
                    'id' => $avatar->id,
                    'name' => $avatar->name,
                    'image_url' => route('avatars.image', $avatar),
                    'primary_color' => $avatar->primary_color,
                    'secondary_color' => $avatar->secondary_color,
                ])
                ->all(),
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

    private function lockActiveAvatar(int $avatarId): void
    {
        $avatar = Avatar::query()
            ->whereKey($avatarId)
            ->active()
            ->lockForUpdate()
            ->first();

        if ($avatar === null) {
            throw ValidationException::withMessages([
                'avatar_id' => 'Cet avatar n’est plus disponible.',
            ]);
        }
    }
}

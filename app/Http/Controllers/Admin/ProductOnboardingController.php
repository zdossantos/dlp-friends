<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductOnboardingStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProductOnboardingSettingRequest;
use App\Models\Avatar;
use App\Models\ProductOnboardingSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProductOnboardingController extends Controller
{
    public function index(): Response
    {
        $eligibleMembers = $this->eligibleMembers();
        $total = (clone $eligibleMembers)->count('users.id');
        $counts = [];

        foreach (ProductOnboardingStatus::cases() as $status) {
            $counts[$status->value] = (clone $eligibleMembers)
                ->when(
                    $status === ProductOnboardingStatus::NotStarted,
                    fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query
                        ->whereNull('product_onboardings.id')
                        ->orWhere('product_onboardings.status', $status->value)),
                    fn (Builder $query): Builder => $query->where('product_onboardings.status', $status->value),
                )
                ->count('users.id');
        }

        /** @var LengthAwarePaginator<int, User> $members */
        $members = (clone $eligibleMembers)
            ->select([
                'users.id',
                'users.email',
                'profiles.display_name',
                DB::raw("COALESCE(product_onboardings.status, 'not_started') AS onboarding_status"),
                'product_onboardings.step',
                DB::raw('COALESCE(product_onboardings.updated_at, users.created_at) AS updated_at'),
            ])
            ->orderByRaw('COALESCE(product_onboardings.updated_at, users.created_at) DESC')
            ->orderByDesc('users.id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $member): array => [
                'id' => $member->id,
                'display_name' => $member->getAttribute('display_name'),
                'email' => $member->email,
                'status' => $member->getAttribute('onboarding_status'),
                'step' => $member->getAttribute('step'),
                'updated_at' => $member->getAttribute('updated_at'),
            ]);

        $setting = ProductOnboardingSetting::current();

        return Inertia::render('Admin/Onboarding/Index', [
            'avatars' => Avatar::query()
                ->active()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (Avatar $avatar): array => [
                    'id' => $avatar->id,
                    'name' => $avatar->name,
                    'image_url' => route('avatars.image', $avatar),
                ]),
            'setting' => [
                'pass_avatar_id' => $setting?->pass_avatar_id,
                'like_avatar_id' => $setting?->like_avatar_id,
            ],
            'stats' => [
                ...$counts,
                'completion_rate' => $total === 0
                    ? 0.0
                    : round(($counts[ProductOnboardingStatus::Completed->value] / $total) * 100, 1),
            ],
            'members' => $members,
        ]);
    }

    public function update(UpdateProductOnboardingSettingRequest $request): RedirectResponse
    {
        ProductOnboardingSetting::query()->updateOrCreate(
            ['id' => ProductOnboardingSetting::SINGLETON_ID],
            $request->validated(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('onboarding.configuration_saved'),
        ]);

        return back();
    }

    /** @return Builder<User> */
    private function eligibleMembers(): Builder
    {
        return User::query()
            ->join('profiles', 'profiles.user_id', '=', 'users.id')
            ->join('avatars', 'avatars.id', '=', 'profiles.avatar_id')
            ->leftJoin('product_onboardings', 'product_onboardings.user_id', '=', 'users.id')
            ->where('users.status', UserStatus::Active->value)
            ->whereNotNull('users.email_verified_at')
            ->where('users.birth_date', '<=', today()->subYears(18))
            ->whereNotNull('profiles.onboarding_completed_at')
            ->where('avatars.is_active', true);
    }
}

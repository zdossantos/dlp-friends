<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $recentRegistrations = User::query()
            ->with('profile.avatar')
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (User $user): array => [
                'email' => $user->email,
                'status' => $user->status->value,
                'profile_completed' => $user->profile?->isComplete() ?? false,
                'registered_at' => $user->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Dashboard', [
            'stats' => [
                'totalAccounts' => User::query()->count(),
                'activeAccounts' => User::query()->where('status', UserStatus::Active)->count(),
                'verifiedAccounts' => User::query()->whereNotNull('email_verified_at')->count(),
                'completedProfiles' => Profile::query()
                    ->whereNotNull('onboarding_completed_at')
                    ->whereHas('avatar', fn (Builder $query) => $query->where('is_active', true))
                    ->count(),
            ],
            'recentRegistrations' => $recentRegistrations,
        ]);
    }
}

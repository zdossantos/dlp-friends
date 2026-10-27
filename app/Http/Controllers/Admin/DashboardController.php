<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DashboardRequest;
use App\Models\Profile;
use App\Models\User;
use App\Support\AdminUsageStatistics;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(DashboardRequest $request, AdminUsageStatistics $statistics): Response
    {
        $to = CarbonImmutable::parse($request->validated('to', CarbonImmutable::today('UTC')->toDateString()), 'UTC');
        $from = CarbonImmutable::parse($request->validated('from', $to->subDays(29)->toDateString()), 'UTC');
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
            'usage' => $statistics->forPeriod($from, $to),
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

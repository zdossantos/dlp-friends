<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Support\FrontendTranslations;
use App\Support\PublicUrls;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'i18n' => [
                'locale' => app()->getLocale(),
                'messages' => FrontendTranslations::messages(),
            ],
            'legal' => [
                'terms_url' => PublicUrls::termsPath(app()->getLocale()),
                'privacy_url' => PublicUrls::privacyPath(app()->getLocale()),
            ],
            'auth' => [
                'user' => function () use ($request): ?array {
                    $user = $request->user()?->loadMissing(['profile', 'roles']);

                    if ($user === null) {
                        return null;
                    }

                    return [
                        'id' => $user->id,
                        'email' => $user->email,
                        'locale' => $user->locale,
                        'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                        'profile' => $user->profile === null ? null : [
                            'display_name' => $user->profile->display_name,
                            'bio' => $user->profile->bio,
                            'visit_frequency' => $user->profile->visit_frequency?->value,
                            'visibility' => $user->profile->visibility->value,
                            'onboarding_completed_at' => $user->profile->onboarding_completed_at?->toIso8601String(),
                        ],
                        'roles' => $user->roles
                            ->map(fn (Role $role): array => ['name' => $role->name->value])
                            ->values()
                            ->all(),
                        'two_factor_enabled' => $user->hasEnabledTwoFactorAuthentication(),
                    ];
                },
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}

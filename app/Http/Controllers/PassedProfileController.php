<?php

namespace App\Http\Controllers;

use App\Actions\CreateSwipe;
use App\Data\PublicMemberData;
use App\Enums\SwipeDecision;
use App\Models\Swipe;
use App\Models\User;
use App\Support\DiscoveryMatchFlash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class PassedProfileController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $viewer */
        $viewer = $request->user();
        $profiles = Swipe::query()->availablePassesFor($viewer)
            ->with(['target.profile.avatar', 'target.roles'])
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(20)
            ->through(function (Swipe $swipe): array {
                $member = $swipe->target;
                $profile = $member->profile;
                $avatar = $profile?->avatar;
                abort_if($profile === null || $avatar === null, 404);

                return [
                    'id' => $member->id,
                    'display_name' => $profile->display_name,
                    'age' => $member->age,
                    'is_admin' => $member->hasRole('admin'),
                    'avatar' => [
                        'id' => $avatar->id,
                        'name' => $avatar->name,
                        'image_url' => route('avatars.image', $avatar),
                        'primary_color' => $avatar->primary_color,
                        'secondary_color' => $avatar->secondary_color,
                    ],
                ];
            });

        return Inertia::render('Discovery/Passed', ['profiles' => $profiles]);
    }

    public function show(Request $request, User $member): Response
    {
        $member->load(['profile.avatar', 'profile.interests', 'roles']);
        abort_unless($member->profile !== null && Gate::allows('viewPassed', $member->profile), 404);

        return Inertia::render('Members/Show', [
            ...PublicMemberData::from($request->user(), $member),
            'backHref' => route('discovery.passed.index', absolute: false),
            'likeHref' => route('discovery.passed.like', $member, absolute: false),
            'likeLabel' => __('discovery.actions.discover'),
        ]);
    }

    public function store(Request $request, User $member, CreateSwipe $action, DiscoveryMatchFlash $flash): RedirectResponse
    {
        $member->load('profile');
        if ($member->profile === null || ! Gate::allows('viewPassed', $member->profile)) {
            throw ValidationException::withMessages(['target' => __('discovery.errors.target_unavailable')]);
        }

        $match = $action->handle($request->user(), $member, SwipeDecision::Like);
        if ($match !== null) {
            $flash->put($request->session(), $match, $member);
        }

        return to_route('discovery.passed.index');
    }
}

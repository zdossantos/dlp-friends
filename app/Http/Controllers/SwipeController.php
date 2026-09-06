<?php

namespace App\Http\Controllers;

use App\Actions\CreateSwipe;
use App\Enums\SwipeDecision;
use App\Http\Requests\StoreSwipeRequest;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class SwipeController extends Controller
{
    public function __invoke(StoreSwipeRequest $request, string $target, CreateSwipe $action): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $targetUser = ctype_digit($target)
            ? User::query()->find((int) $target)
            : null;

        if (! $targetUser instanceof User) {
            throw ValidationException::withMessages([
                'target' => 'Ce profil n’est pas disponible.',
            ]);
        }

        $decision = SwipeDecision::from((string) $request->validated('decision'));
        $match = $action->handle($actor, $targetUser, $decision);

        if ($match !== null) {
            $match->loadMissing('conversation');
            $targetUser->loadMissing('profile.avatar');
            $profile = $targetUser->profile;

            if ($profile instanceof Profile && $profile->avatar !== null && $match->conversation !== null) {
                $avatar = $profile->avatar;
                $request->session()->flash('discovery.match', [
                    'id' => $match->id,
                    'conversationId' => $match->conversation->id,
                    'member' => [
                        'id' => $targetUser->id,
                        'displayName' => $profile->display_name,
                        'avatar' => [
                            'id' => $avatar->id,
                            'name' => $avatar->name,
                            'image_url' => route('avatars.image', $avatar),
                            'primary_color' => $avatar->primary_color,
                            'secondary_color' => $avatar->secondary_color,
                        ],
                    ],
                ]);
            }
        }

        return to_route('discovery.index');
    }
}

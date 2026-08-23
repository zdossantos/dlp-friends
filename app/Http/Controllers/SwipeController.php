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
            $targetUser->loadMissing('profile');
            $profile = $targetUser->profile;

            if ($profile instanceof Profile) {
                $request->session()->flash('discovery.match', [
                    'id' => $match->id,
                    'displayName' => $profile->display_name,
                ]);
            }
        }

        return to_route('discovery.index');
    }
}

<?php

namespace App\Http\Controllers;

use App\Actions\CreateSwipe;
use App\Enums\SwipeDecision;
use App\Http\Requests\StoreSwipeRequest;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class SwipeController extends Controller
{
    public function __invoke(StoreSwipeRequest $request, User $target, CreateSwipe $action): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $decision = SwipeDecision::from((string) $request->validated('decision'));
        $match = $action->handle($actor, $target, $decision);

        if ($match !== null) {
            $target->loadMissing('profile');
            $profile = $target->profile;

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

<?php

namespace App\Http\Controllers;

use App\Actions\CreateSwipe;
use App\Enums\SwipeDecision;
use App\Http\Requests\StoreSwipeRequest;
use App\Models\User;
use App\Support\DiscoveryMatchFlash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class SwipeController extends Controller
{
    public function __invoke(
        StoreSwipeRequest $request,
        string $target,
        CreateSwipe $action,
        DiscoveryMatchFlash $matchFlash,
    ): RedirectResponse {
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
            $matchFlash->put($request->session(), $match, $targetUser);
        }

        return to_route('discovery.index');
    }
}

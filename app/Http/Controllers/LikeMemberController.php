<?php

namespace App\Http\Controllers;

use App\Actions\CreateSwipe;
use App\Enums\SwipeDecision;
use App\Models\User;
use App\Support\DiscoveryMatchFlash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

final class LikeMemberController extends Controller
{
    public function __invoke(
        Request $request,
        User $member,
        CreateSwipe $createSwipe,
        DiscoveryMatchFlash $matchFlash,
    ): RedirectResponse {
        /** @var User $viewer */
        $viewer = $request->user();
        $match = $createSwipe->handle($viewer, $member, SwipeDecision::Like);

        if ($match !== null) {
            $matchFlash->put($request->session(), $match, $member);

            return redirect($this->returnTo($request));
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('discovery.profile_like.success'),
        ]);

        return back();
    }

    private function returnTo(Request $request): string
    {
        $returnTo = $request->string('return_to')->toString();

        return str_starts_with($returnTo, '/') && ! str_starts_with($returnTo, '//')
            ? $returnTo
            : route('discovery.index');
    }
}

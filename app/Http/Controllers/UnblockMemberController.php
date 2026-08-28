<?php

namespace App\Http\Controllers;

use App\Actions\UnblockUser;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class UnblockMemberController extends Controller
{
    public function __invoke(Request $request, User $member, UnblockUser $unblockUser): RedirectResponse
    {
        $member->load('profile.avatar');
        $profile = $member->profile;
        abort_if($profile === null || ! Gate::forUser($request->user())->allows('block', $profile), 404);

        $unblockUser->handle($request->user(), $member);
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('blocking.unblocked', ['name' => $profile->display_name]),
        ]);

        return redirect($this->returnTo($request, $member));
    }

    private function returnTo(Request $request, User $member): string
    {
        $fallback = route('members.show', $member, absolute: false);
        $returnTo = $request->string('return_to')->toString();

        return str_starts_with($returnTo, '/') && ! str_starts_with($returnTo, '//')
            ? $returnTo
            : $fallback;
    }
}

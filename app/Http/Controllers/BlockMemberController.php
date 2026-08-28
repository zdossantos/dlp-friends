<?php

namespace App\Http\Controllers;

use App\Actions\BlockUser;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class BlockMemberController extends Controller
{
    public function __invoke(Request $request, User $member, BlockUser $blockUser): RedirectResponse
    {
        $member->load('profile.avatar');
        $profile = $member->profile;
        abort_if($profile === null || ! Gate::forUser($request->user())->allows('block', $profile), 404);

        $blockUser->handle($request->user(), $member);
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('blocking.completed'),
        ]);

        return to_route('discovery.index');
    }
}

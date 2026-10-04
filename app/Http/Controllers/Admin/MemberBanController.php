<?php

namespace App\Http\Controllers\Admin;

use App\Actions\SetMemberBan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SetMemberBanRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class MemberBanController extends Controller
{
    public function __invoke(SetMemberBanRequest $request, User $member, SetMemberBan $action): RedirectResponse
    {
        $action->handle($request->user(), $member, $request->boolean('banned'), $request->string('reason')->toString());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('moderation.ban_updated')]);

        return back();
    }
}

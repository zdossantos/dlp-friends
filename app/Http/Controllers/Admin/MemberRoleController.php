<?php

namespace App\Http\Controllers\Admin;

use App\Actions\SyncManageableUserRoles;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateMemberRolesRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class MemberRoleController extends Controller
{
    public function __invoke(
        UpdateMemberRolesRequest $request,
        User $member,
        SyncManageableUserRoles $syncManageableUserRoles,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $syncManageableUserRoles->handle($actor, $member, $request->roles());
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('administration.members.roles_updated'),
        ]);

        return redirect()->route('admin.members.index');
    }
}

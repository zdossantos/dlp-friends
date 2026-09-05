<?php

namespace App\Http\Controllers\Admin;

use App\Actions\OpenAdminMemberConversation;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class MemberConversationController extends Controller
{
    public function __invoke(Request $request, User $member, OpenAdminMemberConversation $openConversation): RedirectResponse
    {
        $member->load(['roles', 'profile.avatar']);
        Gate::authorize('startConversation', $member);

        $result = $openConversation->handle($request->user(), $member);

        if (! $result->created) {
            return redirect()->route('conversations.show', $result->conversation);
        }

        $request->session()->flash('admin.members.created_match', [
            'displayName' => $member->profile?->display_name,
            'conversationHref' => route('conversations.show', $result->conversation, absolute: false),
        ]);

        return redirect()->route('admin.members.index');
    }
}

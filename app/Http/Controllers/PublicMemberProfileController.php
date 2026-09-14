<?php

namespace App\Http\Controllers;

use App\Data\PublicMemberData;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class PublicMemberProfileController extends Controller
{
    public function __invoke(Request $request, User $member): Response
    {
        $member->load(['profile.avatar', 'profile.interests', 'roles']);
        $profile = $member->profile;

        abort_if($profile === null || ! Gate::forUser($request->user())->allows('viewPublic', $profile), 404);
        abort_if($profile->avatar === null, 404);

        return Inertia::render('Members/Show', [
            'backHref' => $this->backHref($request, $member),
            ...PublicMemberData::from($request->user(), $member),
        ]);
    }

    private function backHref(Request $request, User $member): string
    {
        $conversationId = $request->integer('conversation');

        if ($conversationId === 0) {
            return route('discovery.index', absolute: false);
        }

        $conversation = Conversation::query()
            ->with('memberMatch')
            ->find($conversationId);

        if ($conversation === null
            || ! Gate::forUser($request->user())->allows('view', $conversation)
            || ! in_array($member->id, [
                $conversation->memberMatch->user_low_id,
                $conversation->memberMatch->user_high_id,
            ], true)) {
            return route('discovery.index', absolute: false);
        }

        return route('conversations.show', $conversation, absolute: false);
    }
}

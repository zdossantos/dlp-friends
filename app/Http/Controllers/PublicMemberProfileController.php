<?php

namespace App\Http\Controllers;

use App\Models\Block;
use App\Models\Conversation;
use App\Models\Interest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class PublicMemberProfileController extends Controller
{
    public function __invoke(Request $request, User $member): Response
    {
        $member->load(['profile.avatar', 'profile.interests']);
        $profile = $member->profile;

        abort_if($profile === null || ! Gate::forUser($request->user())->allows('viewPublic', $profile), 404);
        $avatar = $profile->avatar;
        abort_if($avatar === null, 404);

        return Inertia::render('Members/Show', [
            'backHref' => $this->backHref($request, $member),
            'canUnblock' => Block::query()
                ->where('blocker_user_id', $request->user()->id)
                ->where('blocked_user_id', $member->id)
                ->exists(),
            'member' => [
                'id' => $member->id,
                'display_name' => $profile->display_name,
                'age' => $member->age,
                'avatar' => [
                    'id' => $avatar->id,
                    'name' => $avatar->name,
                    'image_url' => route('avatars.image', $avatar),
                    'primary_color' => $avatar->primary_color,
                    'secondary_color' => $avatar->secondary_color,
                ],
                'bio' => $profile->bio,
                'visit_frequency' => $profile->visit_frequency?->value,
                'interests' => $profile->interests
                    ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
                    ->map(fn (Interest $interest): array => [
                        'id' => $interest->id,
                        'name' => $interest->display_name,
                    ])->values()->all(),
            ],
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

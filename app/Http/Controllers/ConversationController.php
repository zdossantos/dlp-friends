<?php

namespace App\Http\Controllers;

use App\Models\Avatar;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ConversationController extends Controller
{
    public function __invoke(Request $request, Conversation $conversation): Response
    {
        Gate::authorize('view', $conversation);

        /** @var User $member */
        $member = $request->user();
        $conversation->load([
            'memberMatch.lowUser.profile.avatar',
            'memberMatch.highUser.profile.avatar',
        ]);

        $participant = $conversation->memberMatch->lowUser->is($member)
            ? $conversation->memberMatch->highUser
            : $conversation->memberMatch->lowUser;
        $messageCount = $conversation->messages()->count();
        $lastPage = max((int) ceil($messageCount / 10), 1);
        $page = min(max($request->integer('messages', $lastPage), 1), $lastPage);
        $offset = max($messageCount - (($lastPage - $page + 1) * 10), 0);
        $limit = min(10, max($messageCount - (($lastPage - $page) * 10), 0));
        $messageRows = $conversation->messages()
            ->oldest('id')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn (Message $message): array => [
                'id' => $message->id,
                'conversation_id' => $message->conversation_id,
                'author_user_id' => $message->author_user_id,
                'content' => $message->content,
                'created_at' => $message->created_at?->toISOString(),
            ]);
        $messages = new LengthAwarePaginator(
            items: $messageRows,
            total: $messageCount,
            perPage: 10,
            currentPage: $page,
            options: [
                'path' => $request->url(),
                'pageName' => 'messages',
                'query' => $request->query(),
            ],
        );

        $profile = $participant->profile;
        /** @var Avatar $avatar */
        $avatar = $profile->avatar;

        return Inertia::render('Conversations/Show', [
            'conversation' => [
                'id' => $conversation->id,
                'archived_at' => $conversation->archived_at?->toISOString(),
            ],
            'participant' => [
                'id' => $participant->id,
                'display_name' => $profile->display_name,
                'avatar' => [
                    'id' => $avatar->id,
                    'name' => $avatar->name,
                    'image_url' => route('avatars.image', $avatar),
                    'primary_color' => $avatar->primary_color,
                    'secondary_color' => $avatar->secondary_color,
                ],
            ],
            'currentUserId' => $member->id,
            'messages' => Inertia::scroll($messages),
        ]);
    }
}

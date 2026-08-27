<?php

namespace App\Http\Controllers;

use App\Models\Avatar;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ConversationIndexController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $member */
        $member = $request->user();

        $conversations = Conversation::query()
            ->forMember($member)
            ->with([
                'memberMatch.lowUser.profile.avatar',
                'memberMatch.highUser.profile.avatar',
                'latestMessage',
            ])
            ->withMax('messages', 'created_at')
            ->orderByDesc('messages_max_created_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Conversation $conversation) use ($member): array {
                $participant = $conversation->memberMatch->lowUser->is($member)
                    ? $conversation->memberMatch->highUser
                    : $conversation->memberMatch->lowUser;
                $latestMessage = $conversation->getRelations()['latestMessage'] ?? null;

                if (! $latestMessage instanceof Message) {
                    $latestMessage = null;
                }

                $activityAt = $latestMessage instanceof Message
                    ? $latestMessage->created_at
                    : $conversation->created_at;

                return [
                    'id' => $conversation->id,
                    'participant' => $this->participantData($participant),
                    'archived_at' => $conversation->archived_at?->toISOString(),
                    'latest_message' => $this->messageData($latestMessage),
                    'activity_at' => $activityAt?->toISOString(),
                ];
            })
            ->all();

        return Inertia::render('Conversations/Index', [
            'conversations' => $conversations,
        ]);
    }

    /** @return array{id: int, display_name: string, avatar: array{id: int, name: string, image_url: string, primary_color: string, secondary_color: string}} */
    private function participantData(User $participant): array
    {
        $profile = $participant->profile;
        /** @var Avatar $avatar */
        $avatar = $profile->avatar;

        return [
            'id' => $participant->id,
            'display_name' => $profile->display_name,
            'avatar' => [
                'id' => $avatar->id,
                'name' => $avatar->name,
                'image_url' => route('avatars.image', $avatar),
                'primary_color' => $avatar->primary_color,
                'secondary_color' => $avatar->secondary_color,
            ],
        ];
    }

    /** @return array{id: int, conversation_id: int, author_user_id: int, content: string, created_at: string|null}|null */
    private function messageData(?Message $message): ?array
    {
        if ($message === null) {
            return null;
        }

        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'author_user_id' => $message->author_user_id,
            'content' => $message->content,
            'created_at' => $message->created_at?->toISOString(),
        ];
    }
}

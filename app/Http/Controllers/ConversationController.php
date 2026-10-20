<?php

namespace App\Http\Controllers;

use App\Actions\MarkConversationRead;
use App\Models\Avatar;
use App\Models\Conversation;
use App\Models\ConversationStarter;
use App\Models\Message;
use App\Models\User;
use App\Support\MemberPresence;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ConversationController extends Controller
{
    public function __invoke(
        Request $request,
        Conversation $conversation,
        MarkConversationRead $markConversationRead,
        MemberPresence $presence,
    ): Response {
        Gate::authorize('view', $conversation);

        /** @var User $member */
        $member = $request->user();
        $markConversationRead->handle($member, $conversation);
        $conversation->load([
            'memberMatch.lowUser.profile.avatar',
            'memberMatch.highUser.profile.avatar',
        ]);

        $participant = $conversation->memberMatch->lowUser->is($member)
            ? $conversation->memberMatch->highUser
            : $conversation->memberMatch->lowUser;
        $latestMessageId = (int) ($conversation->messages()->max('id') ?? 0);
        $historyBoundary = $request->has('messages_before')
            ? min(max($request->integer('messages_before'), 0), $latestMessageId)
            : $latestMessageId;
        $historyQuery = $conversation->messages()->where('id', '<=', $historyBoundary);
        $messageCount = (clone $historyQuery)->count();
        $perPage = 20;
        $lastPage = max((int) ceil($messageCount / $perPage), 1);
        $page = min(max($request->integer('messages', $lastPage), 1), $lastPage);
        $offset = max($messageCount - (($lastPage - $page + 1) * $perPage), 0);
        $limit = min($perPage, max($messageCount - (($lastPage - $page) * $perPage), 0));
        $messageQuery = $conversation->messages()
            ->withCount('reactions')
            ->withExists([
                'reactions as reacted_by_current_user' => fn ($query) => $query->where('user_id', $member->id),
            ])
            ->oldest('id');
        $serializeMessage = fn (Message $message): array => [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'author_user_id' => $message->author_user_id,
            'content' => $message->content,
            'read_at' => $message->read_at?->toISOString(),
            'created_at' => $message->created_at?->toISOString(),
            'reaction_count' => $message->reactions_count,
            'reacted_by_current_user' => (bool) $message->reacted_by_current_user,
        ];
        $messageRows = (clone $messageQuery)
            ->where('id', '<=', $historyBoundary)
            ->offset($offset)->limit($limit)->get()->map($serializeMessage);
        $messages = new LengthAwarePaginator(
            items: $messageRows,
            total: $messageCount,
            perPage: $perPage,
            currentPage: $page,
            options: [
                'path' => $request->url(),
                'pageName' => 'messages',
                'query' => [
                    ...$request->query(),
                    'messages_before' => $historyBoundary,
                ],
            ],
        );
        $conversationStarters = $latestMessageId === 0
            ? ConversationStarter::query()
                ->where('is_active', true)
                ->inRandomOrder()
                ->limit(3)
                ->get(['id', 'text_fr', 'text_en'])
                ->map(fn (ConversationStarter $starter): array => [
                    'id' => $starter->id,
                    'text' => $member->locale === 'en' ? $starter->text_en : $starter->text_fr,
                ])
                ->values()
            : collect();

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
                'presence' => $presence->forViewer($participant),
            ],
            'currentUserId' => $member->id,
            'canBlock' => ! $participant->hasRole('admin'),
            'timezone' => config('app.timezone'),
            'messages' => Inertia::scroll($messages)->matchOn('data.id'),
            'messageUpdates' => Inertia::optional(fn (): array => (clone $messageQuery)
                ->where('id', '>', max($request->integer('messages_after'), 0))
                ->where('id', '<=', $latestMessageId)
                ->get()->map($serializeMessage)->all()),
            'conversationStarters' => $conversationStarters,
        ]);
    }
}

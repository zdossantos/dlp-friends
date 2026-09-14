<?php

namespace App\Http\Controllers;

use App\Data\EventChatData;
use App\Data\EventDetailData;
use App\Data\EventWorkspaceData;
use App\Http\Requests\EventChatIndexRequest;
use App\Models\Event;
use App\Models\EventChatMessage;
use App\Models\User;
use Inertia\Response;

final class EventChatController extends Controller
{
    public function __construct(private readonly EventWorkspaceData $workspace) {}

    public function __invoke(EventChatIndexRequest $request, Event $event): Response
    {
        /** @var User $viewer */
        $viewer = $request->user();
        $chat = $event->chat;
        $messages = $chat->messages()
            ->with('author.profile')
            ->orderByDesc('id')
            ->simplePaginate(10, ['*'], 'messages')
            ->through(fn (EventChatMessage $message): array => EventChatData::message($message));
        $messages->setCollection($messages->getCollection()->reverse()->values());

        return $this->workspace->render($viewer, 'mine', [
            'kind' => 'chat',
            'event' => EventDetailData::from($event->load('organizer.profile'), $viewer),
            'chat' => EventChatData::chat($chat),
            'messages' => $messages,
        ]);
    }
}

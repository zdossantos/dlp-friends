<?php

namespace App\Http\Controllers;

use App\Actions\SendEventChatMessage;
use App\Http\Requests\StoreEventChatMessageRequest;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class EventChatMessageController extends Controller
{
    public function __invoke(
        StoreEventChatMessageRequest $request,
        Event $event,
        SendEventChatMessage $action,
    ): JsonResponse {
        /** @var User $author */
        $author = $request->user();
        $message = $action->handle(
            $author,
            $event->chat()->firstOrFail(),
            (string) $request->validated('content'),
        );

        return response()->json([
            'data' => [
                'id' => $message->id,
                'event_chat_id' => $message->event_chat_id,
                'author_user_id' => $message->author_user_id,
                'content' => $message->content,
                'created_at' => $message->created_at?->toISOString(),
                'updated_at' => $message->updated_at?->toISOString(),
            ],
        ], Response::HTTP_CREATED);
    }
}

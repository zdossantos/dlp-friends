<?php

namespace App\Http\Controllers;

use App\Actions\SendGroupChatMessage;
use App\Data\GroupChatData;
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
        SendGroupChatMessage $action,
    ): JsonResponse {
        /** @var User $author */
        $author = $request->user();
        $message = $action->handle(
            $author,
            $event->chat()->firstOrFail(),
            (string) $request->validated('content'),
        );

        return response()->json([
            'data' => GroupChatData::message($message, $author),
        ], Response::HTTP_CREATED);
    }
}

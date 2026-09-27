<?php

namespace App\Http\Controllers;

use App\Actions\SetEventChatMessageReaction;
use App\Models\Event;
use App\Models\EventChatMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EventChatMessageReactionController extends Controller
{
    public function store(Request $request, Event $event, EventChatMessage $message, SetEventChatMessageReaction $action): JsonResponse
    {
        return $this->respond($request, $event, $message, $action, true);
    }

    public function destroy(Request $request, Event $event, EventChatMessage $message, SetEventChatMessageReaction $action): JsonResponse
    {
        return $this->respond($request, $event, $message, $action, false);
    }

    private function respond(Request $request, Event $event, EventChatMessage $message, SetEventChatMessageReaction $action, bool $reacted): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => $action->handle($user, $event->chat()->firstOrFail(), $message, $reacted)]);
    }
}

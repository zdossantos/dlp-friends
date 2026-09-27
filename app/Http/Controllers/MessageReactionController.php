<?php

namespace App\Http\Controllers;

use App\Actions\SetMessageReaction;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MessageReactionController extends Controller
{
    public function store(Request $request, Conversation $conversation, Message $message, SetMessageReaction $action): JsonResponse
    {
        return $this->respond($request, $conversation, $message, $action, true);
    }

    public function destroy(Request $request, Conversation $conversation, Message $message, SetMessageReaction $action): JsonResponse
    {
        return $this->respond($request, $conversation, $message, $action, false);
    }

    private function respond(Request $request, Conversation $conversation, Message $message, SetMessageReaction $action, bool $reacted): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => $action->handle($user, $conversation, $message, $reacted)]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Actions\SendMessage;
use App\Http\Requests\StoreMessageRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class MessageController extends Controller
{
    public function __invoke(
        StoreMessageRequest $request,
        Conversation $conversation,
        SendMessage $action,
    ): JsonResponse {
        /** @var User $author */
        $author = $request->user();
        $message = $action->handle(
            $author,
            $conversation,
            (string) $request->validated('content'),
        );

        return response()->json([
            'data' => [
                'id' => $message->id,
                'conversation_id' => $message->conversation_id,
                'author_user_id' => $message->author_user_id,
                'content' => $message->content,
                'created_at' => $message->created_at?->toISOString(),
                'updated_at' => $message->updated_at?->toISOString(),
            ],
        ], Response::HTTP_CREATED);
    }
}

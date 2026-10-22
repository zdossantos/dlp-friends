<?php

namespace App\Http\Controllers;

use App\Actions\SendGroupChatMessage;
use App\Data\GroupChatData;
use App\Http\Requests\StoreTripChatMessageRequest;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class TripChatMessageController extends Controller
{
    public function __invoke(
        StoreTripChatMessageRequest $request,
        Trip $trip,
        SendGroupChatMessage $action,
    ): JsonResponse {
        /** @var User $author */
        $author = $request->user();
        $message = $action->handle(
            $author,
            $trip->chat()->firstOrFail(),
            (string) $request->validated('content'),
        );

        return response()->json([
            'data' => GroupChatData::message($message, $author),
        ], Response::HTTP_CREATED);
    }
}

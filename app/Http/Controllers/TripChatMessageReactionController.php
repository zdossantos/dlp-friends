<?php

namespace App\Http\Controllers;

use App\Actions\SetGroupChatMessageReaction;
use App\Models\GroupChatMessage;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TripChatMessageReactionController extends Controller
{
    public function store(Request $request, Trip $trip, GroupChatMessage $message, SetGroupChatMessageReaction $action): JsonResponse
    {
        return $this->respond($request, $trip, $message, $action, true);
    }

    public function destroy(Request $request, Trip $trip, GroupChatMessage $message, SetGroupChatMessageReaction $action): JsonResponse
    {
        return $this->respond($request, $trip, $message, $action, false);
    }

    private function respond(Request $request, Trip $trip, GroupChatMessage $message, SetGroupChatMessageReaction $action, bool $reacted): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => $action->handle($user, $trip->chat()->firstOrFail(), $message, $reacted)]);
    }
}

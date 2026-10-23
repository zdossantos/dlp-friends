<?php

namespace App\Http\Controllers;

use App\Actions\MarkGroupChatRead;
use App\Http\Requests\StoreTripChatReadRequest;
use App\Models\Trip;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;

final class TripChatReadController extends Controller
{
    public function __invoke(
        StoreTripChatReadRequest $request,
        Trip $trip,
        MarkGroupChatRead $action,
    ): Response {
        /** @var User $reader */
        $reader = $request->user();
        $action->handle(
            $reader,
            $trip->chat,
            (int) $request->validated('last_read_message_id'),
        );

        return response()->noContent();
    }
}

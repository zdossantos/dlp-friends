<?php

namespace App\Http\Controllers;

use App\Actions\MarkEventChatRead;
use App\Http\Requests\StoreEventChatReadRequest;
use App\Models\Event;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;

final class EventChatReadController extends Controller
{
    public function __invoke(
        StoreEventChatReadRequest $request,
        Event $event,
        MarkEventChatRead $action,
    ): Response {
        /** @var User $reader */
        $reader = $request->user();
        $action->handle(
            $reader,
            $event->chat,
            (int) $request->validated('last_read_message_id'),
        );

        return response()->noContent();
    }
}

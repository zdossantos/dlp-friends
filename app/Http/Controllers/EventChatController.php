<?php

namespace App\Http\Controllers;

use App\Data\EventWorkspaceData;
use App\Http\Requests\EventChatIndexRequest;
use App\Models\Event;
use App\Models\User;
use Inertia\Response;

final class EventChatController extends Controller
{
    public function __construct(private readonly EventWorkspaceData $workspace) {}

    public function __invoke(EventChatIndexRequest $request, Event $event): Response
    {
        /** @var User $viewer */
        $viewer = $request->user();

        return $this->workspace->conversation($viewer, $event);
    }
}

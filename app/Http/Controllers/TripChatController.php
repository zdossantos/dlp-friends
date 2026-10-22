<?php

namespace App\Http\Controllers;

use App\Data\TripWorkspaceData;
use App\Http\Requests\TripChatIndexRequest;
use App\Models\Trip;
use App\Models\User;
use Inertia\Response;

final class TripChatController extends Controller
{
    public function __construct(private readonly TripWorkspaceData $workspace) {}

    public function __invoke(TripChatIndexRequest $request, Trip $trip): Response
    {
        /** @var User $viewer */
        $viewer = $request->user();

        return $this->workspace->conversation($viewer, $trip);
    }
}

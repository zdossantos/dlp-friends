<?php

namespace App\Http\Controllers;

use App\Data\EventWorkspaceData;
use App\Http\Requests\EventFilterRequest;
use App\Models\User;
use Inertia\Response;

class MyEventController extends Controller
{
    public function __invoke(EventFilterRequest $request, EventWorkspaceData $workspace): Response
    {
        /** @var User $user */
        $user = $request->user();

        return $workspace->render($user, 'mine', null, $request->filters());
    }
}

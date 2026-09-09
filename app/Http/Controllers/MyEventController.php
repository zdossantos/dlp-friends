<?php

namespace App\Http\Controllers;

use App\Data\EventWorkspaceData;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyEventController extends Controller
{
    public function __invoke(Request $request, EventWorkspaceData $workspace): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Events/Mine', $workspace->mine($user));
    }
}

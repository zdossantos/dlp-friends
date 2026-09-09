<?php

namespace App\Http\Controllers;

use App\Actions\CancelEvent;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class EventCancellationController extends Controller
{
    public function __invoke(Request $request, Event $event, CancelEvent $cancel): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $cancel->handle($user, $event);

        return to_route('events.mine');
    }
}

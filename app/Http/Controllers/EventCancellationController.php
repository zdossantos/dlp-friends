<?php

namespace App\Http\Controllers;

use App\Actions\CancelEvent;
use App\Data\EventWorkspaceData;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class EventCancellationController extends Controller
{
    public function __invoke(
        Request $request,
        Event $event,
        CancelEvent $cancel,
        EventWorkspaceData $workspace,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $cancel->handle($user, $event);

        return redirect()->to($workspace->detailUrl($event, $workspace->context($request)));
    }
}

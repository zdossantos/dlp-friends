<?php

namespace App\Http\Controllers;

use App\Actions\RegisterForEvent;
use App\Actions\WithdrawFromEvent;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class EventRegistrationController extends Controller
{
    public function store(Request $request, Event $event, RegisterForEvent $register): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $register->handle($user, $event);

        return back();
    }

    public function destroy(Request $request, Event $event, WithdrawFromEvent $withdraw): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $withdraw->handle($user, $event);

        return back();
    }
}

<?php

namespace App\Http\Controllers;

use App\Events\PresenceChanged;
use App\Models\User;
use App\Support\MemberPresence;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class PresenceHeartbeatController extends Controller
{
    public function __invoke(Request $request, MemberPresence $presence): Response
    {
        /** @var User $member */
        $member = $request->user();
        $presence->touch($member);
        $freshMember = $member->fresh();
        PresenceChanged::dispatch($freshMember, $freshMember->show_presence);

        return response()->noContent();
    }
}

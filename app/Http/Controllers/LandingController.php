<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LandingController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user()->loadMissing(['profile', 'roles']);

        if (! $user->profile?->isComplete()) {
            return to_route('member-profile.create');
        }

        return $user->hasRole(RoleName::Admin)
            ? to_route('dashboard')
            : to_route('discovery.index');
    }
}

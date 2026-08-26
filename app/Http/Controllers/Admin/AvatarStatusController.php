<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAvatarStatusRequest;
use App\Models\Avatar;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class AvatarStatusController extends Controller
{
    public function __invoke(UpdateAvatarStatusRequest $request, Avatar $avatar): RedirectResponse
    {
        $isActive = $request->boolean('is_active');
        $avatar->update(['is_active' => $isActive]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $isActive ? 'Avatar réactivé.' : 'Avatar archivé.',
        ]);

        return back();
    }
}

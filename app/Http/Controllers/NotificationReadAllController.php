<?php

namespace App\Http\Controllers;

use App\Actions\RecordPartnerAnnouncementRead;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class NotificationReadAllController extends Controller
{
    public function __invoke(Request $request, RecordPartnerAnnouncementRead $recordRead): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->unreadNotifications()
            ->orderBy('id')
            ->get()
            ->each(fn ($notification) => $recordRead->handle($user, $notification));

        return to_route('notifications.index');
    }
}

<?php

namespace App\Http\Controllers;

use App\Actions\RecordPartnerAnnouncementRead;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

final class NotificationMarkReadController extends Controller
{
    public function __invoke(
        Request $request,
        string $notification,
        RecordPartnerAnnouncementRead $recordRead,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        /** @var DatabaseNotification $ownedNotification */
        $ownedNotification = $user->notifications()->findOrFail($notification);

        $recordRead->handle($user, $ownedNotification);

        return back();
    }
}

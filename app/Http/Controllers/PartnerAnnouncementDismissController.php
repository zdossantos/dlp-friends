<?php

namespace App\Http\Controllers;

use App\Actions\DismissPartnerAnnouncement;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

final class PartnerAnnouncementDismissController extends Controller
{
    public function __invoke(
        Request $request,
        string $notification,
        DismissPartnerAnnouncement $dismissAnnouncement,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        /** @var DatabaseNotification $ownedNotification */
        $ownedNotification = $user->notifications()->findOrFail($notification);

        $dismissAnnouncement->handle($user, $ownedNotification);

        // The engagement transaction must be durable before removing its presentation record.
        $ownedNotification->delete();

        return to_route('notifications.index');
    }
}

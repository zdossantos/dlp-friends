<?php

namespace App\Http\Controllers;

use App\Actions\DismissPartnerAnnouncement;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

final class NotificationDestroyController extends Controller
{
    public function __invoke(
        Request $request,
        string $notification,
        DismissPartnerAnnouncement $dismissPartnerAnnouncement,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        /** @var DatabaseNotification $ownedNotification */
        $ownedNotification = $user->notifications()->findOrFail($notification);

        if (($ownedNotification->data['target_type'] ?? null) === 'partner_announcement') {
            $dismissPartnerAnnouncement->handle($user, $ownedNotification);
        }

        $ownedNotification->delete();

        return back();
    }
}

<?php

namespace App\Http\Controllers;

use App\Actions\RecordPartnerAnnouncementRead;
use App\Models\User;
use App\Support\MemberNotificationPresenter;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

final class NotificationReadController extends Controller
{
    public function __invoke(
        Request $request,
        string $notification,
        MemberNotificationPresenter $presenter,
        RecordPartnerAnnouncementRead $recordRead,
    ): Response {
        /** @var User $user */
        $user = $request->user();
        /** @var DatabaseNotification $ownedNotification */
        $ownedNotification = $user->notifications()->findOrFail($notification);
        $recordRead->handle($user, $ownedNotification);
        $targetUrl = $presenter->targetUrl($ownedNotification->data, $user, $ownedNotification);

        if (($ownedNotification->data['target_type'] ?? null) === 'partner_announcement') {
            return Inertia::location($targetUrl);
        }

        return redirect()->to($targetUrl);
    }
}

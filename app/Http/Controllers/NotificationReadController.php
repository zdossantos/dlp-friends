<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\MemberNotificationPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

final class NotificationReadController extends Controller
{
    public function __invoke(
        Request $request,
        string $notification,
        MemberNotificationPresenter $presenter,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        /** @var DatabaseNotification $ownedNotification */
        $ownedNotification = $user->notifications()->findOrFail($notification);
        $ownedNotification->markAsRead();

        return redirect()->to($presenter->targetUrl($ownedNotification->data, $user));
    }
}

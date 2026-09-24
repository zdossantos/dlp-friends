<?php

namespace App\Http\Controllers;

use App\Actions\RecordPartnerAnnouncementRead;
use App\Enums\RoleName;
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

        return to_route(
            match (true) {
                $request->string('context')->toString() === 'admin'
                    && $user->hasRole(RoleName::Admin) => 'admin.notifications.index',
                $user->hasRole(RoleName::User) => 'notifications.index',
                default => 'partner.notifications.index',
            },
        );
    }
}

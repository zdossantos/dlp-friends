<?php

namespace App\Http\Controllers;

use App\Enums\NotificationCategory;
use App\Enums\RoleName;
use App\Http\Requests\NotificationIndexRequest;
use App\Models\User;
use App\Support\MemberNotificationPresenter;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

final class NotificationIndexController extends Controller
{
    public function __invoke(
        NotificationIndexRequest $request,
        MemberNotificationPresenter $presenter,
    ): Response {
        /** @var User $user */
        $user = $request->user();
        $canViewAdministrationNotifications = $user->hasRole(RoleName::Admin);
        $category = $request->validated('category');
        $unread = $request->boolean('unread');
        $notifications = $user->notifications()
            ->when(
                ! $canViewAdministrationNotifications,
                fn ($query) => $query->where(
                    'data->category',
                    '!=',
                    NotificationCategory::Administration->value,
                ),
            )
            ->when($category, fn ($query, string $value) => $query->where('data->category', $value))
            ->when($unread, fn ($query) => $query->whereNull('read_at'))
            ->paginate(20)
            ->withQueryString()
            ->through(fn (DatabaseNotification $notification): array => $presenter->present($notification, $user));

        return Inertia::render('Notifications/Index', [
            'canViewAdministrationNotifications' => $canViewAdministrationNotifications,
            'indexUrl' => match (true) {
                $request->routeIs('admin.notifications.index') => route('admin.notifications.index', absolute: false),
                $request->routeIs('partner.notifications.index') => route('partner.notifications.index', absolute: false),
                default => route('notifications.index', absolute: false),
            },
            'filters' => [
                'category' => $category,
                'unread' => $unread,
            ],
            'notifications' => $notifications,
        ]);
    }
}

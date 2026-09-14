<?php

namespace App\Http\Controllers;

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
        $category = $request->validated('category');
        $unread = $request->boolean('unread');
        $notifications = $user->notifications()
            ->when($category, fn ($query, string $value) => $query->where('data->category', $value))
            ->when($unread, fn ($query) => $query->whereNull('read_at'))
            ->paginate(20)
            ->withQueryString()
            ->through(fn (DatabaseNotification $notification): array => $presenter->present($notification, $user));

        return Inertia::render('Notifications/Index', [
            'filters' => [
                'category' => $category,
                'unread' => $unread,
            ],
            'notifications' => $notifications,
        ]);
    }
}

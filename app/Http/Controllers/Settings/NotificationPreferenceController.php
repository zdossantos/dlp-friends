<?php

namespace App\Http\Controllers\Settings;

use App\Actions\UpdatePartnerNotificationPreference;
use App\Enums\WebPushPreference;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\NotificationPreferenceUpdateRequest;
use App\Models\WebPushSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationPreferenceController extends Controller
{
    public function edit(Request $request): Response
    {
        $preferences = $request->user()->notificationPreferences()->pluck('enabled', 'category')->all();

        return Inertia::render('settings/Notifications', [
            'preferences' => collect(WebPushPreference::cases())->mapWithKeys(
                fn (WebPushPreference $preference): array => [$preference->value => (bool) ($preferences[$preference->value] ?? true)],
            ),
            'partnerAnnouncementsEnabled' => (bool) ($preferences[WebPushPreference::PartnerAnnouncements->value] ?? true),
            'devices' => $request->user()->webPushSubscriptions()->whereNull('revoked_at')->get()->map(fn (WebPushSubscription $device): array => [
                'uuid' => $device->uuid, 'deviceName' => $device->device_name,
                'platform' => $device->platform, 'lastUsedAt' => $device->last_used_at?->toIso8601String(),
            ]),
        ]);
    }

    public function update(NotificationPreferenceUpdateRequest $request, UpdatePartnerNotificationPreference $updatePartner): RedirectResponse
    {
        foreach (WebPushPreference::cases() as $preference) {
            if ($request->has($preference->value)) {
                $enabled = $request->boolean($preference->value);
                $request->user()->notificationPreferences()->updateOrCreate(
                    ['category' => $preference], ['enabled' => $enabled],
                );
                if ($preference === WebPushPreference::PartnerAnnouncements) {
                    $updatePartner->handle($request->user(), $enabled);
                }
            }
        }

        return to_route('notification-preferences.edit');
    }
}

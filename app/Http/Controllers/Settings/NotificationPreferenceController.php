<?php

namespace App\Http\Controllers\Settings;

use App\Actions\UpdatePartnerNotificationPreference;
use App\Enums\RoleName;
use App\Enums\WebPushPreference;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\NotificationPreferenceUpdateRequest;
use App\Models\User;
use App\Models\WebPushSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class NotificationPreferenceController extends Controller
{
    public function edit(Request $request): Response
    {
        $preferences = $request->user()->notificationPreferences()->pluck('enabled', 'category')->all();

        return Inertia::render('settings/Notifications', [
            'preferences' => collect($this->availablePreferences($request->user()))->mapWithKeys(
                fn (WebPushPreference $preference): array => [$preference->value => (bool) ($preferences[$preference->value] ?? true)],
            ),
            'emailPreferences' => [
                'weekly_recap_messages' => $request->user()->weekly_recap_messages,
                'weekly_recap_matches' => $request->user()->weekly_recap_matches,
            ],
            'partnerAnnouncementsEnabled' => (bool) ($preferences[WebPushPreference::PartnerAnnouncements->value] ?? true),
            'vapidPublicKey' => (string) config('services.web_push.public_key', ''),
            'adminNewMemberAlertsEnabled' => $request->user()->hasRole(RoleName::Admin)
                ? $request->user()->admin_new_member_alerts : null,
            'devices' => $request->user()->webPushSubscriptions()->whereNull('revoked_at')->get()->map(fn (WebPushSubscription $device): array => [
                'uuid' => $device->uuid, 'deviceName' => $device->device_name,
                'platform' => $device->platform, 'lastUsedAt' => $device->last_used_at?->toIso8601String(),
            ]),
        ]);
    }

    public function update(NotificationPreferenceUpdateRequest $request, UpdatePartnerNotificationPreference $updatePartner): RedirectResponse
    {
        if ($request->has('admin_new_member_alerts')) {
            DB::transaction(function () use ($request): void {
                $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
                Gate::forUser($user)->authorize('updateNewMemberAlerts', $user);
                $user->forceFill(['admin_new_member_alerts' => $request->boolean('admin_new_member_alerts')])->save();
            });
            $request->user()->refresh();
        }
        $emailPreferences = $request->safe()->only(['weekly_recap_messages', 'weekly_recap_matches']);
        if ($emailPreferences !== []) {
            DB::transaction(function () use ($request, $emailPreferences): void {
                User::query()->lockForUpdate()->findOrFail($request->user()->id)->forceFill($emailPreferences)->save();
            });
            $request->user()->refresh();
        }
        foreach ($this->availablePreferences($request->user()) as $preference) {
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

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('account.settings.notifications.saved'),
        ]);

        return to_route('notification-preferences.edit');
    }

    public function disableAll(Request $request, UpdatePartnerNotificationPreference $updatePartner): RedirectResponse
    {
        DB::transaction(function () use ($request, $updatePartner): void {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $user->forceFill(['weekly_recap_messages' => false, 'weekly_recap_matches' => false])->save();
            if ($user->hasRole(RoleName::Admin)) {
                $user->forceFill(['admin_new_member_alerts' => false])->save();
            }
            foreach ($this->availablePreferences($request->user()) as $preference) {
                $request->user()->notificationPreferences()->updateOrCreate(
                    ['category' => $preference], ['enabled' => false],
                );
            }

            $updatePartner->handle($request->user(), false);
            $request->user()->webPushSubscriptions()->whereNull('revoked_at')->update(['revoked_at' => now()]);
        });

        $request->user()->refresh();
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('account.settings.notifications.disabled_all'),
        ]);

        return to_route('notification-preferences.edit');
    }

    /** @return list<WebPushPreference> */
    private function availablePreferences(User $user): array
    {
        return array_filter(
            WebPushPreference::cases(),
            fn (WebPushPreference $preference): bool => $preference !== WebPushPreference::Administration
                || $user->hasRole(RoleName::Admin),
        );
    }
}

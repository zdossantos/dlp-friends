<?php

namespace App\Http\Controllers\Settings;

use App\Actions\UpdatePartnerNotificationPreference;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PartnerNotificationPreferenceUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartnerNotificationPreferenceController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Notifications', [
            'partnerAnnouncementsEnabled' => $request->user()
                ->partnerNotificationPreference()
                ->value('enabled') ?? true,
        ]);
    }

    public function update(
        PartnerNotificationPreferenceUpdateRequest $request,
        UpdatePartnerNotificationPreference $updatePreference,
    ): RedirectResponse {
        $updatePreference->handle(
            $request->user(),
            $request->boolean('partner_announcements'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('account.settings.notifications.saved'),
        ]);

        return to_route('notification-preferences.edit');
    }
}

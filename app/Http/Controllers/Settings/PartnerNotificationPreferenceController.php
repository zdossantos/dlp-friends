<?php

namespace App\Http\Controllers\Settings;

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
            'partnerAnnouncementsEnabled' => (bool) $request->user()
                ->partnerNotificationPreference()
                ->value('enabled'),
        ]);
    }

    public function update(PartnerNotificationPreferenceUpdateRequest $request): RedirectResponse
    {
        $request->user()->partnerNotificationPreference()->updateOrCreate([], [
            'enabled' => $request->boolean('partner_announcements'),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('account.settings.notifications.saved'),
        ]);

        return to_route('notification-preferences.edit');
    }
}

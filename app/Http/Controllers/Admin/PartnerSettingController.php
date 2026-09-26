<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePartnerSettingRequest;
use App\Models\PartnerSetting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class PartnerSettingController extends Controller
{
    public function __invoke(UpdatePartnerSettingRequest $request): RedirectResponse
    {
        PartnerSetting::current()->update([
            'cooldown_days' => $request->cooldownDays(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('administration.partner_announcements.cooldown_saved'),
        ]);

        return to_route('admin.partner-announcements.index');
    }
}

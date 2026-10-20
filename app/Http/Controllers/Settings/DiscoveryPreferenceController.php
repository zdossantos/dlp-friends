<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\DiscoveryPreferenceUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class DiscoveryPreferenceController extends Controller
{
    public function update(DiscoveryPreferenceUpdateRequest $request): RedirectResponse
    {
        $preferences = $request->validated();
        $request->user()->forceFill([
            'discovery_min_age' => $preferences['min_age'],
            'discovery_max_age' => $preferences['max_age'],
            'discovery_min_common_interests' => $preferences['min_common_interests'],
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('account.settings.discovery.saved')]);

        return to_route('account.edit');
    }
}

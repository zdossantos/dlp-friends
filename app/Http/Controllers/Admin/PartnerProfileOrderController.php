<?php

namespace App\Http\Controllers\Admin;

use App\Actions\UpdatePublishedPartnerOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderPartnerProfilesRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class PartnerProfileOrderController extends Controller
{
    public function __invoke(
        OrderPartnerProfilesRequest $request,
        UpdatePublishedPartnerOrder $updateOrder,
    ): RedirectResponse {
        $updateOrder->handle($request->orderedIds());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('administration.partners.order_updated'),
        ]);

        return to_route('admin.partner-profiles.index');
    }
}

<?php

namespace App\Http\Controllers;

use App\Actions\ReportTripDeparture;
use App\Http\Requests\StoreTripDepartureReportRequest;
use App\Models\TripDeparture;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class TripDepartureReportController extends Controller
{
    public function __invoke(StoreTripDepartureReportRequest $request, TripDeparture $departure, ReportTripDeparture $action): RedirectResponse
    {
        $action->handle($request->user(), $departure, $request->string('details')->toString());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('moderation.reported')]);

        return back();
    }
}

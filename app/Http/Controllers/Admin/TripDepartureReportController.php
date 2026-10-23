<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CloseTripDepartureReport;
use App\Data\TripDepartureReportData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CloseTripDepartureReportRequest;
use App\Models\ModerationAudit;
use App\Models\TripDepartureReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class TripDepartureReportController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', TripDepartureReport::class);
        $target = $request->has('target') ? User::query()->with('profile')->findOrFail($request->integer('target')) : null;
        $status = $request->has('closed') ? ($request->boolean('closed') ? 'closed' : 'open') : ($target === null ? 'open' : 'all');
        $query = TripDepartureReport::query()->when($target, fn (Builder $query) => $query->where('target_user_id', $target->id));
        $counts = ['open' => (clone $query)->whereNull('closed_at')->count(), 'closed' => (clone $query)->whereNotNull('closed_at')->count()];

        return Inertia::render('Admin/TripDepartureReports/Index', [
            'status' => $status, 'counts' => $counts,
            'target' => $target === null ? null : ['id' => $target->id, 'name' => $target->profile->display_name ?? __('moderation.deleted_member')],
            'reports' => $query->with(['reporter.profile', 'target.profile', 'target.roles'])
                ->when($status === 'closed', fn (Builder $query) => $query->whereNotNull('closed_at'))
                ->when($status === 'open', fn (Builder $query) => $query->whereNull('closed_at'))
                ->latest('id')->paginate(20)->withQueryString()->through(fn (TripDepartureReport $report): array => TripDepartureReportData::detail($report)),
        ]);
    }

    public function show(Request $request, TripDepartureReport $report): Response
    {
        Gate::authorize('view', $report);
        ModerationAudit::query()->create(['actor_user_id' => $request->user()->id, 'target_user_id' => $report->target_user_id, 'trip_departure_report_id' => $report->id, 'operation' => 'view']);

        return Inertia::render('Admin/TripDepartureReports/Show', ['report' => TripDepartureReportData::detail($report)]);
    }

    public function close(CloseTripDepartureReportRequest $request, TripDepartureReport $report, CloseTripDepartureReport $action): RedirectResponse
    {
        $action->handle($request->user(), $report, $request->string('decision')->toString());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('moderation.report_closed')]);

        return back();
    }
}

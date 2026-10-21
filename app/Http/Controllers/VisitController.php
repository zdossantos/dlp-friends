<?php

namespace App\Http\Controllers;

use App\Data\VisitWorkspaceData;
use App\Http\Requests\StoreVisitRequest;
use App\Http\Requests\UpdateVisitRequest;
use App\Http\Requests\VisitIndexRequest;
use App\Models\Visit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class VisitController extends Controller
{
    public function index(VisitIndexRequest $request, VisitWorkspaceData $data): Response
    {
        return Inertia::render('Visits/Index', [...$data->build($request->user(), $request->filters()), 'memberProfile' => $data->profile($request->user(), $request->filters(), $request->filled('profile') ? $request->integer('profile') : null, $request->validated('profile_day')), 'profileCloseHref' => $request->fullUrlWithoutQuery(['profile', 'profile_day'])]);
    }

    public function store(StoreVisitRequest $request): RedirectResponse
    {
        $request->user()->visits()->create($request->validated());

        return redirect()->back(fallback: route('visits.index'));
    }

    public function update(UpdateVisitRequest $request, Visit $visit): RedirectResponse
    {
        $visit->update($request->validated());

        return redirect()->back(fallback: route('visits.index'));
    }

    public function destroy(Visit $visit): RedirectResponse
    {
        Gate::authorize('delete', $visit);
        $visit->delete();

        return redirect()->back(fallback: route('visits.index'));
    }
}

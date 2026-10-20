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

class VisitController extends Controller
{
    public function index(VisitIndexRequest $request, VisitWorkspaceData $data): \Inertia\Response
    {
        return Inertia::render('Visits/Index', $data->build($request->user(), $request->filters()));
    }

    public function store(StoreVisitRequest $request): RedirectResponse
    {
        $request->user()->visits()->create($request->validated());

        return to_route('visits.index');
    }

    public function update(UpdateVisitRequest $request, Visit $visit): RedirectResponse
    {
        $visit->update($request->validated());

        return to_route('visits.index');
    }

    public function destroy(Visit $visit): RedirectResponse
    {
        Gate::authorize('delete', $visit);
        $visit->delete();

        return to_route('visits.index');
    }
}

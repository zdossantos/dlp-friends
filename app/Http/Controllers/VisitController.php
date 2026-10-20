<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVisitRequest;
use App\Http\Requests\UpdateVisitRequest;
use App\Models\Visit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class VisitController extends Controller
{
    public function index(): Response
    {
        return response('');
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

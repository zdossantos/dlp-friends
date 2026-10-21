<?php

namespace App\Http\Controllers;

use App\Data\VisitWorkspaceData;
use App\Http\Requests\VisitIndexRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

final class VisitAttendeeController
{
    public function __invoke(VisitIndexRequest $request, VisitWorkspaceData $data): Response
    {
        $viewer = $request->user();
        $filters = $request->filters();
        $people = $data->attendees($viewer, $filters, CarbonImmutable::parse($filters['day'], 'Europe/Paris'))->paginate(30)->withQueryString();

        return Inertia::render('Visits/Index', [...$data->build($viewer, $filters), 'memberProfile' => $data->profile($request->user(), $request->filters(), $request->filled('profile') ? $request->integer('profile') : null, $request->validated('profile_day')), 'profileCloseHref' => $request->fullUrlWithoutQuery(['profile', 'profile_day']), 'panel' => ['kind' => 'attendees', 'date' => $filters['day'], 'members' => ['data' => $people->getCollection()->map(fn (User $user): array => $data->member($user, $viewer))->all(), 'links' => ['prev' => $people->previousPageUrl(), 'next' => $people->nextPageUrl()], 'meta' => ['total' => $people->total(), 'current_page' => $people->currentPage(), 'last_page' => $people->lastPage()]]]]);
    }
}

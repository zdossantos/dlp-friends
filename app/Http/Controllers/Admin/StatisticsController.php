<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StatisticsRequest;
use App\Support\AdminUsageStatistics;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class StatisticsController extends Controller
{
    public function __invoke(StatisticsRequest $request, AdminUsageStatistics $statistics): Response
    {
        $to = CarbonImmutable::parse($request->validated('to', CarbonImmutable::today('UTC')->toDateString()), 'UTC');
        $from = CarbonImmutable::parse($request->validated('from', $to->subDays(29)->toDateString()), 'UTC');

        return Inertia::render('Admin/Statistics', [
            'usage' => $statistics->forPeriod($from, $to),
        ]);
    }
}

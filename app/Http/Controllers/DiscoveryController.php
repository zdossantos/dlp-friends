<?php

namespace App\Http\Controllers;

use App\Data\DiscoveryProfileData;
use App\Http\Requests\DiscoveryRequest;
use App\Models\User;
use App\Services\DiscoveryService;
use Inertia\Inertia;
use Inertia\Response;

class DiscoveryController extends Controller
{
    public function __invoke(DiscoveryRequest $request, DiscoveryService $service): Response
    {
        /** @var User $user */
        $user = $request->user();
        $queue = array_values(array_map('intval', $request->validated('queue', $request->session()->get('discovery.queue', []))));

        return Inertia::render('Discovery/Index', [
            'suggestions' => Inertia::defer(
                function () use ($request, $service, $user, $queue): array {
                    $suggestions = $service->for($user, $queue);
                    $request->session()->put('discovery.queue', $suggestions->pluck('userId')->all());

                    return $suggestions
                        ->map(fn (DiscoveryProfileData $profile): array => $profile->toArray())
                        ->values()
                        ->all();
                },
            ),
        ]);
    }
}

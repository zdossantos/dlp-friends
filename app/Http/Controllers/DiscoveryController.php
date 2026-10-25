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
        $queue = array_values(array_map('intval', $request->validated('queue', [])));

        return Inertia::render('Discovery/Index', [
            'suggestions' => Inertia::defer(
                function () use ($service, $user, $queue): array {
                    return $service->for($user, $queue)
                        ->map(fn (DiscoveryProfileData $profile): array => $profile->toArray())
                        ->values()
                        ->all();
                },
            ),
        ]);
    }
}

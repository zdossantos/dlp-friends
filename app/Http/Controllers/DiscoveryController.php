<?php

namespace App\Http\Controllers;

use App\Data\DiscoveryProfileData;
use App\Http\Requests\DiscoveryRequest;
use App\Models\User;
use App\Services\DiscoveryService;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class DiscoveryController extends Controller
{
    public function __invoke(DiscoveryRequest $request, DiscoveryService $service): Response
    {
        /** @var User $user */
        $user = $request->user();
        $queueKey = 'discovery:queue:'.$user->id.':'.$request->session()->getId();
        $queue = array_values(array_map('intval', $request->validated('queue', Cache::get($queueKey, []))));

        return Inertia::render('Discovery/Index', [
            'suggestions' => Inertia::defer(
                function () use ($service, $user, $queue, $queueKey): array {
                    $suggestions = $service->for($user, $queue);
                    Cache::put($queueKey, $suggestions->pluck('userId')->all(), now()->addMinutes((int) config('session.lifetime')));

                    return $suggestions
                        ->map(fn (DiscoveryProfileData $profile): array => $profile->toArray())
                        ->values()
                        ->all();
                },
            ),
        ]);
    }
}

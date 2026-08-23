<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DiscoveryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DiscoveryController extends Controller
{
    public function __invoke(Request $request, DiscoveryService $service): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Discovery/Index', [
            'suggestions' => Inertia::defer(
                fn (): array => $service
                    ->for($user)
                    ->take(5)
                    ->map(fn ($profile): array => $profile->toArray())
                    ->values()
                    ->all(),
            ),
            'match' => fn (): mixed => $request->session()->pull('discovery.match'),
        ]);
    }
}

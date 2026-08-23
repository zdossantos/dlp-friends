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
            'suggestion' => Inertia::defer(
                fn (): ?array => $service->for($user)->first()?->toArray(),
            ),
            'match' => fn (): mixed => $request->session()->pull('discovery.match'),
        ]);
    }
}

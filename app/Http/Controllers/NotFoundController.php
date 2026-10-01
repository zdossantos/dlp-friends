<?php

namespace App\Http\Controllers;

use App\Support\AuthenticatedHome;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class NotFoundController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $pathExistsForAnotherMethod = false;

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! $route->isFallback && $route->matches($request, false) && ! $route->matches($request)) {
                $pathExistsForAnotherMethod = true;
                break;
            }
        }

        abort_if($pathExistsForAnotherMethod, 405);

        $homeUrl = $request->user() === null
            ? route('landing.show', ['locale' => app()->getLocale()], false)
            : route(AuthenticatedHome::routeName($request->user()), absolute: false);

        return Inertia::render('Errors/NotFound', [
            'homeUrl' => $homeUrl,
            'inertiaHome' => $request->user() !== null,
        ])
            ->toResponse($request)
            ->setStatusCode(404);
    }
}

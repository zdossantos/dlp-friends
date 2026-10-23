<?php

namespace App\Http\Controllers;

use App\Http\Requests\TripPlaceSearchRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TripPlaceSearchController extends Controller
{
    public function __invoke(TripPlaceSearchRequest $request): JsonResponse
    {
        try {
            $response = Http::acceptJson()->connectTimeout(2)->timeout(4)
                ->get('https://photon.komoot.io/api/', [
                    'q' => $request->string('query')->toString(),
                    'lang' => app()->getLocale() === 'en' ? 'en' : 'fr',
                    'limit' => 5,
                ]);
        } catch (ConnectionException) {
            return $this->unavailable();
        }

        $features = $response->json('features');
        if (! $response->successful() || ! is_array($features)) {
            return $this->unavailable();
        }

        $labels = [];
        foreach (array_slice($features, 0, 5) as $feature) {
            $properties = is_array($feature) ? ($feature['properties'] ?? null) : null;
            if (! is_array($properties)) {
                continue;
            }
            $part = static fn (string $key): string => is_string($properties[$key] ?? null) ? trim($properties[$key]) : '';
            $street = trim($part('housenumber').' '.$part('street'));
            $city = trim($part('postcode').' '.$part('city'));
            $label = implode(', ', array_unique(array_filter([$part('name'), $street, $city, $part('country')])));
            if ($label !== '') {
                $labels[] = Str::limit($label, 160, '');
            }
        }

        return response()->json(['suggestions' => array_values(array_unique($labels))])->header('Cache-Control', 'no-store, private');
    }

    private function unavailable(): JsonResponse
    {
        return response()->json(['suggestions' => []], 503)->header('Cache-Control', 'no-store, private');
    }
}

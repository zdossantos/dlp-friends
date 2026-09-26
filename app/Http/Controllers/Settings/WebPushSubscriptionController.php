<?php

namespace App\Http\Controllers\Settings;

use App\Actions\UpsertWebPushSubscription;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\WebPushSubscriptionStoreRequest;
use App\Models\WebPushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebPushSubscriptionController extends Controller
{
    public function store(WebPushSubscriptionStoreRequest $request, UpsertWebPushSubscription $upsert): JsonResponse
    {
        [$subscription, $created] = $upsert->handle($request->user(), $request->subscription());

        return response()->json($this->resource($subscription), $created ? 201 : 200);
    }

    public function destroy(Request $request, string $uuid): Response
    {
        $subscription = $request->user()->webPushSubscriptions()->where('uuid', $uuid)->firstOrFail();
        $subscription->update(['revoked_at' => now()]);

        return response()->noContent();
    }

    /** @return array{uuid: string, device_name: string|null, platform: string|null, last_used_at: string|null} */
    private function resource(WebPushSubscription $subscription): array
    {
        return [
            'uuid' => $subscription->uuid, 'device_name' => $subscription->device_name,
            'platform' => $subscription->platform, 'last_used_at' => $subscription->last_used_at?->toIso8601String(),
        ];
    }
}

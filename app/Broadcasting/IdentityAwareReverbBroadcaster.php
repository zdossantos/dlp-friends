<?php

namespace App\Broadcasting;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class IdentityAwareReverbBroadcaster extends PusherBroadcaster
{
    public function validAuthenticationResponse($request, $result)
    {
        $user = $request->user();
        if (! $user instanceof User || $user->fresh()?->status !== UserStatus::Active) {
            throw new AccessDeniedHttpException;
        }
        if (str_starts_with($request->channel_name, 'private-')) {
            return $this->decodePusherResponse($request, $this->pusher->authorizeChannel($request->channel_name, $request->socket_id, json_encode(['user_id' => (string) $user->id], JSON_THROW_ON_ERROR)));
        }

        return parent::validAuthenticationResponse($request, $result);
    }
}

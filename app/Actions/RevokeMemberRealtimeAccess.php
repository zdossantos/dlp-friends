<?php

namespace App\Actions;

use App\Broadcasting\IdentityAwareReverbBroadcaster;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Throwable;

final class RevokeMemberRealtimeAccess
{
    public function handle(User $user): void
    {
        if (config('broadcasting.default') !== 'reverb') {
            return;
        }
        try {
            $broadcaster = Broadcast::connection('reverb');
            if ($broadcaster instanceof IdentityAwareReverbBroadcaster) {
                $broadcaster->getPusher()->terminateUserConnections((string) $user->id);
            }
        } catch (Throwable) {
            // No identity, credential or exception payload is logged. Channels fail closed independently.
            Log::warning('Realtime account revocation unavailable.');
        }
    }
}

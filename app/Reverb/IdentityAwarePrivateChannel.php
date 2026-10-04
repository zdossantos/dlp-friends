<?php

namespace App\Reverb;

use App\Enums\UserStatus;
use App\Models\User;
use Laravel\Reverb\Contracts\Connection;
use Laravel\Reverb\Protocols\Pusher\Channels\PrivateChannel;
use Laravel\Reverb\Protocols\Pusher\Exceptions\ConnectionUnauthorized;

final class IdentityAwarePrivateChannel extends PrivateChannel
{
    public function subscribe(Connection $connection, ?string $auth = null, ?string $data = null): void
    {
        $this->verify($connection, $auth, $data);
        $identity = $data === null ? null : json_decode($data, true);
        if (! is_array($identity) || ! isset($identity['user_id']) || ! is_string($identity['user_id']) || ! ctype_digit($identity['user_id']) || ! $this->active($identity['user_id'])) {
            throw new ConnectionUnauthorized;
        }
        parent::subscribe($connection, $auth, $data);
    }

    /** @param array<string, mixed> $payload */
    public function broadcast(array $payload, ?Connection $except = null): void
    {
        if ($except !== null && ! $this->active($this->find($except)?->data('user_id'))) {
            $except->disconnect();

            return;
        }
        $message = json_encode($payload, JSON_THROW_ON_ERROR);
        foreach ($this->connections() as $subscription) {
            if (! $this->active($subscription->data('user_id'))) {
                $connection = $subscription->connection();
                $connection->disconnect();
                $this->connections->remove($connection);

                continue;
            }
            if ($except !== null && $except->id() === $subscription->connection()->id()) {
                continue;
            }
            $subscription->send($message);
        }
    }

    /** @param array<string, mixed> $payload */
    public function broadcastToAll(array $payload): void
    {
        $this->broadcast($payload);
    }

    private function active(mixed $id): bool
    {
        return is_string($id) && ctype_digit($id) && User::query()->whereKey($id)->where('status', UserStatus::Active)->withCurrentTerms()->exists();
    }
}

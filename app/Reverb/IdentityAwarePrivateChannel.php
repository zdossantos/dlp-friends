<?php

namespace App\Reverb;

use App\Enums\UserStatus;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
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
        if ($except !== null && ! $this->active($this->find($except)?->data('user_id'), $payload)) {
            $except->disconnect();

            return;
        }
        $message = json_encode($payload, JSON_THROW_ON_ERROR);
        foreach ($this->connections() as $subscription) {
            if (! $this->active($subscription->data('user_id'), $payload)) {
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

    /** @param array<string, mixed>|null $payload */
    private function active(mixed $id, ?array $payload = null): bool
    {
        if (! is_string($id) || ! ctype_digit($id)) {
            return false;
        }

        $query = User::query()->whereKey($id)->where('status', UserStatus::Active)->withCurrentTerms();

        $conversationId = str_starts_with($this->name(), 'private-conversation.')
            ? substr($this->name(), strlen('private-conversation.')) : null;
        if ($conversationId === null && ($payload['event'] ?? null) === 'message.sent') {
            $data = $payload['data'] ?? null;
            $data = is_string($data) ? json_decode($data, true) : $data;
            $conversationId = is_array($data) ? ($data['conversation_id'] ?? null) : null;
            if (! is_int($conversationId) && (! is_string($conversationId) || ! ctype_digit($conversationId))) {
                return false;
            }
        }
        if ($conversationId === null && ! app()->environment('beta')) {
            return $query->exists();
        }

        $user = $query->when(app()->environment('beta'), fn ($query) => $query->with('roles'))->first();
        if ($user === null || (app()->environment('beta') && ! Gate::forUser($user)->allows('accessBeta', User::class))) {
            return false;
        }
        if ($conversationId === null) {
            return true;
        }

        $conversation = Conversation::query()->with(['memberMatch.lowUser', 'memberMatch.highUser'])
            ->find($conversationId);

        return $conversation !== null && Gate::forUser($user)->allows('send', $conversation);
    }
}

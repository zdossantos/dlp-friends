<?php

namespace App\Reverb;

use Laravel\Reverb\Events\ChannelCreated;
use Laravel\Reverb\Protocols\Pusher\Channels\Channel;
use Laravel\Reverb\Protocols\Pusher\Managers\ArrayChannelManager;

final class IdentityAwareChannelManager extends ArrayChannelManager
{
    public function findOrCreate(string $channelName): Channel
    {
        if (! str_starts_with($channelName, 'private-')) {
            return parent::findOrCreate($channelName);
        }
        if ($channel = $this->find($channelName)) {
            return $channel;
        }
        $channel = new IdentityAwarePrivateChannel($channelName);
        $this->applications[$this->application->id()][$channelName] = $channel;
        ChannelCreated::dispatch($channel);

        return $channel;
    }
}

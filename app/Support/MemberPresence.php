<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

final class MemberPresence
{
    private const ONLINE_SECONDS = 45;

    private const ACTIVITY_WRITE_SECONDS = 60;

    public function touch(User $member): bool
    {
        if ($member->show_presence === false) {
            Cache::forget($this->onlineKey($member));

            return false;
        }

        $becameOnline = Cache::add($this->onlineKey($member), true, self::ONLINE_SECONDS);
        Cache::put($this->onlineKey($member), true, self::ONLINE_SECONDS);

        if (Cache::add("presence:activity:{$member->id}", true, self::ACTIVITY_WRITE_SECONDS)) {
            $member->forceFill(['last_active_at' => now()])->saveQuietly();
        }

        return $becameOnline;
    }

    public function forget(User $member): void
    {
        Cache::forget($this->onlineKey($member));
    }

    /** @return array{online: bool, last_active_at: string|null}|null */
    public function forViewer(User $participant): ?array
    {
        if ($participant->show_presence === false) {
            return null;
        }

        return [
            'online' => Cache::has($this->onlineKey($participant)),
            'last_active_at' => $participant->last_active_at?->toISOString(),
        ];
    }

    private function onlineKey(User $member): string
    {
        return "presence:user:{$member->id}";
    }
}

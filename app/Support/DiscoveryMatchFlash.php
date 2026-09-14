<?php

namespace App\Support;

use App\Models\MemberMatch;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Contracts\Session\Session;

final class DiscoveryMatchFlash
{
    public function put(Session $session, MemberMatch $match, User $member): void
    {
        $match->loadMissing('conversation');
        $member->loadMissing('profile.avatar');
        $profile = $member->profile;

        if (! $profile instanceof Profile || $profile->avatar === null || $match->conversation === null) {
            return;
        }

        $avatar = $profile->avatar;
        $session->flash('discovery.match', [
            'id' => $match->id,
            'conversationId' => $match->conversation->id,
            'member' => [
                'id' => $member->id,
                'displayName' => $profile->display_name,
                'avatar' => [
                    'id' => $avatar->id,
                    'name' => $avatar->name,
                    'image_url' => route('avatars.image', $avatar),
                    'primary_color' => $avatar->primary_color,
                    'secondary_color' => $avatar->secondary_color,
                ],
            ],
        ]);
    }
}

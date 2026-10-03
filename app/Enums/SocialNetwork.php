<?php

namespace App\Enums;

enum SocialNetwork: string
{
    case Instagram = 'instagram';
    case Facebook = 'facebook';
    case TikTok = 'tiktok';
    case YouTube = 'youtube';
    case X = 'x';

    /** @return list<string> */
    public function hosts(): array
    {
        return match ($this) {
            self::Instagram => ['instagram.com', 'www.instagram.com'],
            self::Facebook => ['facebook.com', 'www.facebook.com', 'm.facebook.com'],
            self::TikTok => ['tiktok.com', 'www.tiktok.com'],
            self::YouTube => ['youtube.com', 'www.youtube.com', 'youtu.be'],
            self::X => ['x.com', 'www.x.com', 'twitter.com', 'www.twitter.com'],
        };
    }
}

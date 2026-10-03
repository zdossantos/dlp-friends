<?php

namespace App\Enums;

enum SocialLinksVisibility: string
{
    case Hidden = 'hidden';
    case Matches = 'matches';
    case Members = 'members';
}

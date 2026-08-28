<?php

namespace App\Enums;

enum ProductOnboardingStep: string
{
    case PassDemo = 'pass_demo';
    case LikeDemo = 'like_demo';
    case MatchDemo = 'match_demo';
    case ConversationDemo = 'conversation_demo';
}


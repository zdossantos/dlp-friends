<?php

namespace App\Data;

use App\Models\Conversation;

final readonly class AdminMemberConversationResult
{
    public function __construct(
        public Conversation $conversation,
        public bool $created,
    ) {}
}

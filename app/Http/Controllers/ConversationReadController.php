<?php

namespace App\Http\Controllers;

use App\Actions\MarkConversationRead;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ConversationReadController extends Controller
{
    public function __invoke(
        Request $request,
        Conversation $conversation,
        MarkConversationRead $markConversationRead,
    ): Response {
        /** @var User $member */
        $member = $request->user();
        $markConversationRead->handle($member, $conversation);

        return response()->noContent();
    }
}

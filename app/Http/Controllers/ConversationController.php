<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class ConversationController extends Controller
{
    public function __invoke(Conversation $conversation): Response
    {
        Gate::authorize('view', $conversation);

        return response()->noContent();
    }
}
